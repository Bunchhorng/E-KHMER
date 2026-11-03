<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\AddressResource;
use App\Models\BakongPayment;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private CartService $cart,
        private InventoryService $inventory,
        private CouponService $coupon,
        private ShopOrderService $shopOrders,
        private ShopOrderFulfillmentService $fulfillment,
        private OrderNumberService $orderNumbers,
    ) {}

    /** Read-only preview; uses the same calculator as order creation. */
    public function quote(Cart $cart, array $payload = [], ?User $user = null): array
    {
        $items = $this->cart->items($cart);
        $cart->setRelation('items', $items);

        return $this->calculate($cart, $items, $payload, $user)['quote'];
    }

    private function calculate(Cart $cart, Collection $items, array $payload, ?User $user, bool $checkout = false): array
    {
        $base = $this->cart->totals($cart, $items);
        $coupon = null;
        $eligibleSubtotal = 0.0;
        $rawDiscount = 0.0;
        $discount = 0.0;
        if (! empty($payload['coupon_code'])) {
            $coupon = $this->coupon->validate($user, $payload['coupon_code'], $cart, lockForUpdate: $checkout);
            $eligibleSubtotal = $this->coupon->eligibleSubtotal($coupon, $cart);
            $rawDiscount = $this->coupon->rawDiscountFor($coupon, $eligibleSubtotal);
            $discount = $this->coupon->discountFor($coupon, $eligibleSubtotal);
        }

        $available = $items->filter(fn ($item) => $this->cart->availability($item)['available']);
        $groups = $available->groupBy(fn ($item) => $item->variant->product->shop_id ?? 0)->sortKeys();
        $shippingByShop = [];
        $shippingMethods = [];
        $shippingMethod = null;
        $delivery = [];
        $shippingComplete = $groups->isNotEmpty();

        if ($checkout && ! is_array($payload['shipping_methods'] ?? null)) {
            // Preserve the existing single-method API: its fee is charged once.
            $shippingMethod = ShippingMethod::where('is_active', true)->findOrFail($payload['shipping_method_id']);
            if ($shippingMethod->shop_id !== null && $available->contains(fn ($item) => (int) $item->variant->product->shop_id !== (int) $shippingMethod->shop_id)) {
                throw ValidationException::withMessages(['shipping_method_id' => 'This delivery method belongs to a different shop.']);
            }
            $shippingCents = (int) round((float) $shippingMethod->price * 100);
        } else {
            foreach ($groups as $shopId => $group) {
                $methodId = $payload['shipping_methods'][$shopId] ?? null;
                $method = $methodId === null && ! $checkout
                    ? ShippingMethod::where('is_active', true)->where(fn ($q) => $q->whereNull('shop_id')->orWhere('shop_id', $shopId))->orderBy('price')->orderBy('id')->first()
                    : ShippingMethod::where('is_active', true)->find($methodId);
                if ($method === null && $methodId === null && ! $checkout) {
                    $shippingComplete = false;

                    continue;
                }
                if (! $method || ($method->shop_id !== null && (int) $method->shop_id !== (int) $shopId)) {
                    throw ValidationException::withMessages(['shipping_methods' => 'Choose an available delivery method for every shop.']);
                }
                $shippingMethods[$shopId] = $method;
                $shippingByShop[$shopId] = (float) $method->price;
                $delivery[] = ['shop_id' => (int) $shopId, 'id' => $method->id, 'name' => $method->name, 'price' => (float) $method->price];
            }
            $shippingMethod = reset($shippingMethods) ?: null;
            $shippingCents = array_sum(array_map(fn ($price) => (int) round($price * 100), $shippingByShop));
        }

        $subtotalCents = (int) round($base['subtotal'] * 100);
        $discountCents = (int) round($discount * 100);
        // Existing policy: 10% of discounted goods; shipping is added after tax.
        $taxCents = (int) round(($subtotalCents - $discountCents) / 10);
        $quote = [
            'currency' => 'USD', 'subtotal' => $subtotalCents / 100,
            'eligible_subtotal' => $eligibleSubtotal, 'raw_discount' => $rawDiscount,
            'discount_amount' => $discountCents / 100, 'tax_amount' => $taxCents / 100,
            'shipping_amount' => $shippingCents / 100,
            'total' => ($subtotalCents - $discountCents + $taxCents + $shippingCents) / 100,
            'coupon' => $coupon, 'shipping_methods' => $delivery, 'shipping_complete' => $shippingComplete,
            'can_checkout' => $shippingComplete && $items->isNotEmpty() && $available->count() === $items->count(),
        ];
        $signature = [
            'cart' => $cart->id,
            'items' => $items->sortBy('id')->map(fn ($item) => [
                $item->id, $item->product_variant_id, (int) $item->quantity, $item->variant?->product?->shop_id,
                (int) round((float) ($item->variant?->price ?? $item->variant?->product?->price ?? 0) * 100),
            ])->values()->all(),
            'amounts' => [$subtotalCents, $discountCents, $taxCents, $shippingCents],
            'coupon' => $coupon === null ? null : [$coupon->id, $coupon->shop_id, $rawDiscount],
            'delivery' => $delivery,
        ];
        $quote['fingerprint'] = hash_hmac('sha256', json_encode($signature, JSON_THROW_ON_ERROR), (string) config('app.key'));

        return compact('quote', 'coupon', 'shippingByShop', 'shippingMethods', 'shippingMethod');
    }

    /**
     * Begin the checkout, reserving stock and snapshotting the order.
     */
    public function begin(array $payload, ?User $user = null): Order
    {
        return DB::transaction(function () use ($payload, $user): Order {
            $cart = $payload['cart'] ?? $this->cart->forUser($user, $payload['session_id'] ?? null);
            // carts.user_id and carts.session_id are unique: serialize all new
            // checkouts for this owner, even with different/no idempotency keys.
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            app(CheckoutProtection::class)->assertCapacity($cart, $user);

            $cartItems = $cart->items()->with(CartService::ITEM_RELATIONS)->lockForUpdate()->get();
            $cart->setRelation('items', $cartItems);

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['message' => 'Your cart is empty']);
            }

            $this->cart->assertPurchasable($cartItems);

            $calculation = $this->calculate($cart, $cartItems, $payload, $user, checkout: true);
            $quote = $calculation['quote'];
            if (! empty($payload['quote_fingerprint']) && ! hash_equals($quote['fingerprint'], $payload['quote_fingerprint'])) {
                throw ValidationException::withMessages(['quote' => 'Totals changed. Review the updated total before placing your order.'])->status(409);
            }
            $coupon = $calculation['coupon'];
            $couponId = $coupon?->id;
            $couponCode = $coupon?->code;
            $subtotal = $quote['subtotal'];
            $discount = $quote['discount_amount'];
            $taxAmount = $quote['tax_amount'];
            $shippingAmount = $quote['shipping_amount'];
            $total = $quote['total'];
            $shippingByShop = $calculation['shippingByShop'];
            $shippingMethods = $calculation['shippingMethods'];
            $shippingMethod = $calculation['shippingMethod'];

            if (($payload['payment_method'] ?? '') === 'bakong') {
                if (! config('ecommerce.bakong_token')) {
                    throw ValidationException::withMessages(['payment' => 'Bakong is not configured yet. Choose cash on delivery.']);
                }
                foreach ($cartItems as $item) {
                    $shop = $item->variant?->product?->shop;
                    if (! $shop?->bakong_account_id || ! $shop->bakong_merchant_name || ! $shop->bakong_merchant_city) {
                        throw ValidationException::withMessages(['payment' => 'A shop in your cart has not enabled Bakong. Choose cash on delivery.']);
                    }
                }
            }

            $reservations = [];
            foreach ($cartItems as $item) {
                $variant = $item->variant;

                $purchasable = $variant !== null && ProductVariant::query()
                    ->whereKey($variant->id)
                    ->where('is_active', true)
                    ->whereHas('product', fn ($query) => $query->active())
                    ->exists();

                if (! $purchasable || (int) $item->quantity < 1) {
                    throw ValidationException::withMessages([
                        'cart' => ['One or more items in your cart are no longer available.'],
                    ]);
                }

                $reservations[(int) $item->product_variant_id] = (int) $item->quantity;
            }

            if (! $this->inventory->reserveMany($reservations)) {
                throw ValidationException::withMessages(['message' => 'Insufficient stock. Some items in your cart are no longer available.']);
            }

            $address = $payload['address'] ?? [];
            $snapshot = AddressResource::fromArray($address);

            $email = $user?->email ?? ($payload['email'] ?? ($address['email'] ?? null));
            $phone = $user?->phone ?? ($address['phone'] ?? null);
            $customerName = $user?->name ?? ($address['full_name'] ?? null);

            $order = $this->orderNumbers->createParent([
                'user_id' => $user?->id,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_UNPAID,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total' => $total,
                'currency' => 'USD',
                'shipping_address' => $snapshot,
                'billing_address' => $snapshot,
                'email' => $email,
                'phone' => $phone,
                'customer_name' => $customerName,
                'note' => $payload['note'] ?? null,
                'placed_at' => now(),
            ]);
            $order->trackingEvents()->create([
                'status' => Order::STATUS_PENDING,
                'description' => 'Order placed',
            ]);

            foreach ($cartItems as $item) {
                $variant = $item->variant;
                $product = $variant?->product;

                $unitPrice = round((float) ($variant?->price ?? $product?->price ?? 0), 2);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'shop_id' => $product?->shop_id,
                    'product_name' => $product?->name ?? 'Product',
                    'variant_label' => $variant !== null ? $this->variantLabel($variant) : null,
                    'sku' => $variant?->sku ?? $product?->sku,
                    'image_path' => $product?->cover_image,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $variant?->cost_price,
                    'quantity' => (int) $item->quantity,
                    'line_total' => round($unitPrice * (int) $item->quantity, 2),
                ]);
            }

            $this->shopOrders->split($order, $shippingByShop, $coupon?->shop_id);

            if (($payload['payment_method'] ?? '') === 'bakong' && $order->shopOrders()->where('total', '<=', 0)->exists()) {
                throw ValidationException::withMessages(['payment' => 'A shop total is zero. Choose cash on delivery to complete this order without a bank transfer.']);
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $payload['payment_method'] ?? 'card',
                'status' => Payment::STATUS_PENDING,
                'amount' => $total,
                'provider_data' => [
                    'session_id' => $payload['session_token'] ?? null,
                    // Allows confirm() to clear the exact guest or user cart
                    // without trusting a client-supplied cart identifier.
                    'cart_id' => $cart->id,
                ],
            ]);

            foreach ($order->shopOrders()->get() as $shopOrder) {
                Shipment::create([
                    'order_id' => $order->id,
                    'shop_order_id' => $shopOrder->id,
                    'shipping_method_id' => ($shippingMethods[$shopOrder->shop_id] ?? $shippingMethod)->id,
                    'status' => Shipment::STATUS_PENDING,
                    'address_snapshot' => $snapshot,
                ]);
            }
            if ($order->items()->whereNull('shop_order_id')->exists()) {
                Shipment::create([
                    'order_id' => $order->id,
                    'shipping_method_id' => ($shippingMethods[0] ?? $shippingMethod)->id,
                    'status' => Shipment::STATUS_PENDING,
                    'address_snapshot' => $snapshot,
                ]);
            }

            if ($coupon !== null && $discount > 0) {
                $this->coupon->applyUsage($coupon, $order, $user);
            }

            return $order->load(['items.shop', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents', 'payment', 'shipments', 'trackingEvents.changedBy']);
        }, 3);
    }

    /**
     * Confirm payment and permanently deduct reserved stock.
     */
    public function confirm(Order $order, ?string $transactionId = null, bool $verifiedBakong = false): Order
    {
        return DB::transaction(function () use ($order, $transactionId, $verifiedBakong): Order {
            $order = Order::with(['items', 'payment'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $fromStatus = $order->status;

            if (! in_array($order->payment_status, [Order::PAYMENT_UNPAID, 'partial'], true) || $order->status !== Order::STATUS_PENDING) {
                throw ValidationException::withMessages(['message' => 'Order already settled']);
            }

            $payment = $order->payment;
            if ($payment === null) {
                throw ValidationException::withMessages(['message' => 'No payment record found']);
            }

            $isCashOnDelivery = $payment->method === 'cod';

            if ($order->placed_at->copy()->addMinutes((int) config('ecommerce.reservation_minutes', 15))->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages(['payment' => 'This checkout has expired. Start a new checkout.']);
            }

            if ($payment->method === 'bakong' && (! $verifiedBakong || BakongPayment::where('order_id', $order->id)->where('status', '!=', 'paid')->exists() || ! BakongPayment::where('order_id', $order->id)->exists())) {
                throw ValidationException::withMessages(['payment' => 'Complete and verify every shop payment before confirming this order.']);
            }

            if (! $isCashOnDelivery && ! ($payment->method === 'bakong' && $verifiedBakong) && config('ecommerce.payment_mode') !== 'sandbox') {
                throw ValidationException::withMessages([
                    'message' => 'Online payment confirmation is disabled until a payment gateway is configured.',
                ]);
            }

            $items = [];
            foreach ($order->items as $item) {
                $items[(int) $item->product_variant_id] = (int) $item->quantity;
            }
            $this->inventory->deductMany($items);

            if (! $isCashOnDelivery) {
                $payment->status = Payment::STATUS_COMPLETED;
                $payment->transaction_id = $transactionId ?? 'PAY-'.strtoupper(Str::random(12));
                $payment->paid_at = now();
                $payment->save();

                PaymentTransaction::create([
                    'payment_id' => $payment->id,
                    'type' => 'capture',
                    'status' => 'success',
                    'amount' => round((float) $payment->amount, 2),
                    'reference' => $payment->transaction_id,
                ]);

                $order->payment_status = Order::PAYMENT_PAID;
            }

            $order->status = Order::STATUS_CONFIRMED;
            $order->save();
            $this->fulfillment->synchronizeAll($order, Order::STATUS_CONFIRMED);

            $order->trackingEvents()->create([
                'from_status' => $fromStatus,
                'status' => Order::STATUS_CONFIRMED,
                'description' => trim(($order->payment_status === Order::PAYMENT_PAID ? 'Payment received' : 'Order confirmed').'.'),
            ]);

            $order->notifyCustomer(new OrderPlacedNotification($order));

            $cartId = $payment->provider_data['cart_id'] ?? null;
            if (is_numeric($cartId)) {
                Cart::find((int) $cartId)?->items()->delete();
            }

            return $order->load(['items.shop', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents', 'payment', 'shipments', 'trackingEvents.changedBy']);
        });
    }

    /**
     * Release a reservation and cancel an unpaid pending order.
     */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::with(['items', 'payment'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $fromStatus = $order->status;

            if (! in_array($order->payment_status, [Order::PAYMENT_UNPAID, 'partial'], true) || $order->status !== Order::STATUS_PENDING) {
                return;
            }

            $items = [];
            foreach ($order->items as $item) {
                $items[(int) $item->product_variant_id] = (int) $item->quantity;
            }
            $this->inventory->releaseMany($items);
            $this->coupon->releaseUsage($order);

            $order->status = Order::STATUS_CANCELLED;
            $paid = BakongPayment::where('order_id', $order->id)->where('status', 'paid')->update(['status' => 'refund_required']);
            if ($paid || BakongPayment::where('order_id', $order->id)->where('status', 'refund_required')->exists()) {
                $order->payment_status = 'refund_pending';
                $order->payment?->update(['status' => 'refund_pending']);
            }
            if ($order->payment?->method === 'cod') {
                $order->payment->update(['status' => Payment::STATUS_FAILED]);
            }
            $order->note = trim(($order->note ? $order->note.' ' : '').'reservation released');
            $order->save();
            $this->fulfillment->synchronizeAll($order, Order::STATUS_CANCELLED);

            $order->trackingEvents()->create([
                'from_status' => $fromStatus,
                'status' => Order::STATUS_CANCELLED,
                'description' => 'Order cancelled',
            ]);
        });
    }

    /**
     * Release reservations for stale unpaid pending orders. Cron entry that the
     * scheduler job and the live site's checkout keep-alive delayed confirm path
     * rely on.
     */
    public function expireStaleReservations(int $minutes = 15): int
    {
        if (func_num_args() === 0) {
            $minutes = (int) config('ecommerce.reservation_minutes', 15);
        }

        $cutoff = now()->subMinutes($minutes);
        $count = 0;

        $orders = Order::where('status', Order::STATUS_PENDING)
            ->whereIn('payment_status', [Order::PAYMENT_UNPAID, 'partial'])
            ->where('placed_at', '<=', $cutoff)
            ->get();

        foreach ($orders as $order) {
            $this->release($order);
            $count++;
        }

        return $count;
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $parts = [];

        foreach ($variant->attributeValues as $vav) {
            $value = $vav->value;
            $attribute = $value?->attribute;

            if ($value === null) {
                continue;
            }

            $attributeName = $attribute?->name ?? 'Option';
            $parts[] = $attributeName.': '.$value->value;
        }

        if (count($parts) === 0 && $variant->name !== null) {
            return $variant->name;
        }

        return implode(', ', $parts);
    }
}
