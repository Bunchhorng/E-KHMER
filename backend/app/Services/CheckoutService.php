<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\AddressResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private CartService $cart,
        private InventoryService $inventory,
        private CouponService $coupon,
        private OrderNumberGenerator $orderNumber,
    ) {}

    /**
     * Begin the checkout, reserving stock and snapshotting the order.
     */
    public function begin(array $payload, ?User $user = null): Order
    {
        return DB::transaction(function () use ($payload, $user): Order {
            $cart = $payload['cart'] ?? $this->cart->forUser($user, $payload['session_id'] ?? null);

            if ($cart->items()->count() === 0) {
                throw ValidationException::withMessages(['message' => 'Your cart is empty']);
            }

            $totals = $this->cart->totals($cart);
            $subtotal = round((float) $totals['subtotal'], 2);

            $discount = 0.0;
            $couponId = null;
            $couponCode = null;
            $coupon = null;

            if (! empty($payload['coupon_code'])) {
                $coupon = $this->coupon->validate($user, $payload['coupon_code'], $subtotal);
                $discount = round($this->coupon->discountFor($coupon, $subtotal), 2);
                $couponId = $coupon->id;
                $couponCode = $coupon->code;
            }

            $shippingMethod = ShippingMethod::where('is_active', true)->findOrFail($payload['shipping_method_id']);
            $shippingAmount = round((float) $shippingMethod->price, 2);

            $taxAmount = round(($subtotal - $discount) * 0.10, 2);
            $total = round($subtotal - $discount + $taxAmount + $shippingAmount, 2);

            $reservations = [];
            foreach ($cart->items as $item) {
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

            $order = new Order([
                'order_number' => $this->generateOrderNumber(),
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
            $order->save();

            $order->trackingEvents()->create([
                'status' => Order::STATUS_PENDING,
                'description' => 'Order placed',
            ]);

            foreach ($cart->items as $item) {
                $variant = $item->variant()->with(['product.images', 'attributeValues.value.attribute'])->first();
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
                    'quantity' => (int) $item->quantity,
                    'line_total' => round($unitPrice * (int) $item->quantity, 2),
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $payload['payment_method'] ?? 'card',
                'status' => Payment::STATUS_PENDING,
                'amount' => $total,
                'provider_data' => ['session_id' => $payload['session_token'] ?? null],
            ]);

            Shipment::create([
                'order_id' => $order->id,
                'shipping_method_id' => $shippingMethod->id,
                'status' => Shipment::STATUS_PENDING,
                'address_snapshot' => $snapshot,
            ]);

            if ($coupon !== null && $discount > 0) {
                $this->coupon->applyUsage($coupon, $order, $user);
            }

            return $order->load(['items.shop', 'payment', 'shipments', 'trackingEvents']);
        });
    }

    /**
     * Confirm payment and permanently deduct reserved stock.
     */
    public function confirm(Order $order, ?string $transactionId = null): Order
    {
        return DB::transaction(function () use ($order, $transactionId): Order {
            $order = Order::with(['items', 'payment'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->payment_status !== Order::PAYMENT_UNPAID || $order->status !== Order::STATUS_PENDING) {
                throw ValidationException::withMessages(['message' => 'Order already settled']);
            }

            $payment = $order->payment;
            if ($payment === null) {
                throw ValidationException::withMessages(['message' => 'No payment record found']);
            }

            $isCashOnDelivery = $payment->method === 'cod';

            if (! $isCashOnDelivery && config('ecommerce.payment_mode') !== 'sandbox') {
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

            $order->trackingEvents()->create([
                'status' => Order::STATUS_CONFIRMED,
                'description' => trim(($order->payment_status === Order::PAYMENT_PAID ? 'Payment received' : 'Order confirmed').'.'),
            ]);

            if ($order->user_id !== null) {
                $user = $order->user()->first();
                $user->notify(new OrderPlacedNotification($order));
                $this->cart->clear($this->cart->forUser($user, null));
            }

            return $order->load(['items.shop', 'payment', 'shipments', 'trackingEvents']);
        });
    }

    /**
     * Release a reservation and cancel an unpaid pending order.
     */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::with(['items', 'payment'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->payment_status !== Order::PAYMENT_UNPAID || $order->status !== Order::STATUS_PENDING) {
                return;
            }

            $items = [];
            foreach ($order->items as $item) {
                $items[(int) $item->product_variant_id] = (int) $item->quantity;
            }
            $this->inventory->releaseMany($items);
            $this->coupon->releaseUsage($order);

            $order->status = Order::STATUS_CANCELLED;
            $order->note = trim(($order->note ? $order->note.' ' : '').'reservation released');
            $order->save();

            $order->trackingEvents()->create([
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
            ->where('payment_status', Order::PAYMENT_UNPAID)
            ->where('placed_at', '<', $cutoff)
            ->get();

        foreach ($orders as $order) {
            $this->release($order);
            $count++;
        }

        return $count;
    }

    private function generateOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $number = $this->orderNumber->next();

            if (! Order::withTrashed()->where('order_number', $number)->exists()) {
                return $number;
            }
        }

        return $this->orderNumber->next();
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
