<?php

namespace Database\Seeders;

use App\Models\BakongPayment;
use App\Models\BusinessExpense;
use App\Models\CheckoutAttempt;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    private const SOURCE_NOTE = '[DEMO SYNTHETIC ORDER]';

    private const CARRIERS = [
        'Virak Buntham Express',
        'J&T Express Cambodia',
        'CE Express',
        'GrabExpress Phnom Penh',
    ];

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo orders may only be seeded in local or testing environments.');

        $admin = User::where('email', 'admin@ekhmer.com')->firstOrFail();
        $primaryCustomer = User::where('email', 'customer@ekhmer.com')->firstOrFail();
        $customers = User::where('role', User::ROLE_CUSTOMER)
            ->where('email', '!=', 'seller@ekhmer.com')
            ->with('addresses')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $u) => $u->addresses->isNotEmpty())
            ->values();

        // Only pick variants belonging to active products & active shops with comfortable stock.
        $variantsByShop = ProductVariant::with(['product.shop', 'image', 'inventory'])
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->active())
            ->whereHas('inventory', fn ($q) => $q->whereRaw('quantity - reserved_quantity >= 8'))
            ->orderBy('id')
            ->get()
            ->groupBy('shop_id');

        if ($variantsByShop->isEmpty()) {
            return;
        }

        $shopIds = $variantsByShop->keys()->values()->all();
        $shippingByShop = ShippingMethod::where('is_active', true)->orderBy('price')->get()->groupBy('shop_id');
        $coupons = Coupon::where('is_active', true)->whereNull('shop_id')->orderBy('id')->get();

        $scenarios = $this->scenarioMatrix();

        foreach ($scenarios as $index => $scenario) {
            $orderNumber = 'EKH-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            if (Order::withTrashed()->where('order_number', $orderNumber)->exists()) {
                continue;
            }

            $customer = $index < 8
                ? $primaryCustomer
                : $customers[($index * 2) % $customers->count()];

            $address = $customer->addresses->firstWhere('is_default', true) ?? $customer->addresses->first();
            $snapshot = $address->only([
                'full_name', 'phone', 'address_line1', 'address_line2',
                'city', 'state', 'postal_code', 'country',
            ]);

            $shopCount = $scenario['multi_shop'] ? min(3, max(2, count($shopIds))) : 1;
            $selectedLines = [];
            for ($s = 0; $s < $shopCount; $s++) {
                $targetShopId = $shopIds[($index + $s * 3) % count($shopIds)];
                $pool = $variantsByShop[$targetShopId];
                $variant = $pool[($index + $s) % $pool->count()];
                $qty = ($index + $s) % 2 === 0 ? 2 : 1;
                $selectedLines[] = ['variant' => $variant, 'quantity' => $qty];
            }

            $subtotalCents = 0;
            $shopSubtotals = [];
            foreach ($selectedLines as $line) {
                $lineCents = (int) round((float) $line['variant']->price * 100) * $line['quantity'];
                $subtotalCents += $lineCents;
                $sId = (int) $line['variant']->shop_id;
                $shopSubtotals[$sId] = ($shopSubtotals[$sId] ?? 0) + $lineCents;
            }

            $coupon = ($scenario['use_coupon'] && $coupons->isNotEmpty())
                ? $coupons[$index % $coupons->count()]
                : null;
            $discountCents = 0;
            if ($coupon !== null && $subtotalCents >= (int) round((float) ($coupon->min_order_amount ?? 0) * 100)) {
                $discountCents = $coupon->type === 'percentage'
                    ? (int) round($subtotalCents * (float) $coupon->value / 100)
                    : (int) round((float) $coupon->value * 100);
                if ($coupon->max_discount_amount !== null) {
                    $discountCents = min($discountCents, (int) round((float) $coupon->max_discount_amount * 100));
                }
                $discountCents = min($discountCents, $subtotalCents);
            } else {
                $coupon = null;
            }

            $shopDiscounts = $this->allocate($discountCents, $shopSubtotals);
            $shopNet = [];
            foreach ($shopSubtotals as $sId => $subCents) {
                $shopNet[$sId] = $subCents - $shopDiscounts[$sId];
            }

            $taxCents = (int) round(($subtotalCents - $discountCents) / 10);
            $shopTaxes = $this->allocate($taxCents, $shopNet);

            $shopShippingMethods = [];
            $shippingTotalCents = 0;
            foreach (array_keys($shopSubtotals) as $sId) {
                $method = $shippingByShop->get($sId)?->first();
                if ($method === null) {
                    continue;
                }
                $shopShippingMethods[$sId] = $method;
                $shippingTotalCents += (int) round((float) $method->price * 100);
            }

            $totalCents = $subtotalCents - $discountCents + $taxCents + $shippingTotalCents;

            $placedAt = $scenario['order_status'] === Order::STATUS_PENDING
                ? now()->subMinutes(5)
                : now()->subDays(2 + ($index % 25))->subHours($index % 12);

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $customer->id,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'status' => $scenario['order_status'],
                'payment_status' => $scenario['payment_status'],
                'subtotal' => $subtotalCents / 100,
                'discount_amount' => $discountCents / 100,
                'tax_amount' => $taxCents / 100,
                'shipping_amount' => $shippingTotalCents / 100,
                'total' => $totalCents / 100,
                'currency' => 'USD',
                'shipping_address' => $snapshot,
                'billing_address' => $snapshot,
                'email' => $customer->email,
                'phone' => $snapshot['phone'],
                'customer_name' => $customer->name,
                'placed_at' => $placedAt,
                'note' => self::SOURCE_NOTE.' '.$scenario['label'],
            ]);

            $order->trackingEvents()->create([
                'status' => Order::STATUS_PENDING,
                'description' => 'Order placed (synthetic demo fixture).',
                'created_at' => $placedAt,
            ]);
            if ($scenario['order_status'] !== Order::STATUS_PENDING) {
                $order->trackingEvents()->create([
                    'from_status' => Order::STATUS_PENDING,
                    'status' => $scenario['order_status'],
                    'description' => 'Order status: '.$scenario['order_status'],
                    'changed_by' => $admin->id,
                    'created_at' => $placedAt->copy()->addHours(2),
                ]);
            }

            if ($coupon !== null && $scenario['order_status'] !== Order::STATUS_CANCELLED) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $customer->id,
                    'order_id' => $order->id,
                    'redeemed_at' => $placedAt,
                ]);
                $coupon->increment('used_count');
            }

            $shopOrderModels = [];
            $shopIndex = 0;
            foreach ($shopSubtotals as $sId => $subCents) {
                $shopIndex++;
                $method = $shopShippingMethods[$sId] ?? ShippingMethod::firstOrFail();
                $shipCents = (int) round((float) $method->price * 100);
                $shopTotalCents = $shopNet[$sId] + $shopTaxes[$sId] + $shipCents;
                $shopStatus = $scenario['split_shipped_and_delivered'] && $shopIndex === 1
                    ? Order::STATUS_DELIVERED
                    : $scenario['order_status'];

                $shopOrder = ShopOrder::create([
                    'order_id' => $order->id,
                    'shop_id' => $sId,
                    'shop_order_number' => $orderNumber.'-'.$shopIndex,
                    'status' => $shopStatus,
                    'subtotal' => $subCents / 100,
                    'discount_amount' => $shopDiscounts[$sId] / 100,
                    'tax_amount' => $shopTaxes[$sId] / 100,
                    'shipping_amount' => $shipCents / 100,
                    'total' => $shopTotalCents / 100,
                ]);
                $shopOrderModels[$sId] = $shopOrder;

                $shipmentStatus = match ($shopStatus) {
                    Order::STATUS_SHIPPED => Shipment::STATUS_SHIPPED,
                    Order::STATUS_DELIVERED => Shipment::STATUS_DELIVERED,
                    default => Shipment::STATUS_PENDING,
                };
                $shippedAt = in_array($shopStatus, [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED], true)
                    ? $placedAt->copy()->addDay()
                    : null;
                $deliveredAt = $shopStatus === Order::STATUS_DELIVERED
                    ? $placedAt->copy()->addDays(3)
                    : null;

                Shipment::create([
                    'order_id' => $order->id,
                    'shop_order_id' => $shopOrder->id,
                    'shipping_method_id' => $method->id,
                    'tracking_number' => in_array($shopStatus, [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED], true)
                        ? 'EKH-TRK-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).'-'.$shopIndex
                        : null,
                    'carrier' => self::CARRIERS[($index + $shopIndex) % count(self::CARRIERS)],
                    'status' => $shipmentStatus,
                    'address_snapshot' => $snapshot,
                    'shipped_at' => $shippedAt,
                    'delivered_at' => $deliveredAt,
                ]);

                $shopOrder->trackingEvents()->create([
                    'order_id' => $order->id,
                    'status' => $shopStatus,
                    'description' => 'Synthetic demo shop status: '.$shopStatus,
                    'changed_by' => $shopStatus === Order::STATUS_PENDING ? null : $admin->id,
                    'created_at' => $deliveredAt ?? $shippedAt ?? $placedAt,
                ]);
            }

            $createdItems = [];
            foreach ($selectedLines as $line) {
                $variant = $line['variant'];
                $qty = $line['quantity'];
                $sId = (int) $variant->shop_id;
                $unitCents = (int) round((float) $variant->price * 100);

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'shop_order_id' => $shopOrderModels[$sId]->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'shop_id' => $sId,
                    'product_name' => $variant->product->name,
                    'variant_label' => $variant->name,
                    'sku' => $variant->sku,
                    'image_path' => $variant->image?->image_path ?? $variant->product->cover_image,
                    'unit_price' => $unitCents / 100,
                    'unit_cost' => $variant->cost_price,
                    'quantity' => $qty,
                    'line_total' => ($unitCents * $qty) / 100,
                ]);
                $createdItems[] = $orderItem;

                // Synchronize demo inventory counts cleanly without calling external gateways.
                if ($scenario['order_status'] === Order::STATUS_PENDING) {
                    Inventory::where('product_variant_id', $variant->id)->increment('reserved_quantity', $qty);
                } elseif ($scenario['order_status'] !== Order::STATUS_CANCELLED) {
                    Inventory::where('product_variant_id', $variant->id)->increment('sold_count', $qty);
                }
            }

            $this->seedPaymentsAndReturns($order, $shopOrderModels, $createdItems, $scenario, $customer, $admin, $placedAt);

            CheckoutAttempt::create([
                'owner_key' => hash('sha256', 'user:'.$customer->id),
                'request_key' => 'demo-checkout-'.$orderNumber,
                'payload_hash' => hash('sha256', $orderNumber),
                'order_id' => $order->id,
            ]);
        }

        // Seed 3 months of realistic shop operating expenses across active storefronts.
        $this->seedShopOperatingExpenses($admin);
    }

    /**
     * @param  array<int, ShopOrder>  $shopOrderModels
     * @param  list<OrderItem>  $createdItems
     * @param  array<string, mixed>  $scenario
     */
    private function seedPaymentsAndReturns(
        Order $order,
        array $shopOrderModels,
        array $createdItems,
        array $scenario,
        User $customer,
        User $admin,
        $placedAt,
    ): void {
        $isBakong = $scenario['method'] === 'bakong';
        $isPaid = in_array($scenario['payment_status'], [Order::PAYMENT_PAID, 'partially_refunded', Order::PAYMENT_REFUNDED], true);
        $paymentRowStatus = match ($scenario['payment_status']) {
            Order::PAYMENT_PAID, 'partially_refunded' => Payment::STATUS_COMPLETED,
            Order::PAYMENT_REFUNDED => Payment::STATUS_REFUNDED,
            Order::PAYMENT_FAILED => Payment::STATUS_FAILED,
            default => Payment::STATUS_PENDING,
        };

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $scenario['method'],
            'status' => $paymentRowStatus,
            'transaction_id' => $isPaid || $isBakong ? $order->order_number.'-PAY' : null,
            'amount' => $order->total,
            'paid_at' => $isPaid ? $placedAt->copy()->addMinutes(4) : null,
            'provider_data' => ['demo' => true, 'synthetic_fixture' => true],
        ]);

        if ($isBakong) {
            foreach ($shopOrderModels as $shopId => $shopOrder) {
                $shopModel = Shop::find($shopId);
                $bakongStatus = $isPaid ? 'paid' : ($scenario['order_status'] === Order::STATUS_CANCELLED ? 'expired' : 'pending');
                BakongPayment::create([
                    'order_id' => $order->id,
                    'shop_order_id' => $shopOrder->id,
                    'shop_id' => $shopId,
                    'receiver_account' => $shopModel?->bakong_account_id ?? 'demo@aclb',
                    'payer_account' => $isPaid ? 'demo-payer@example.invalid' : null,
                    'amount' => $shopOrder->total,
                    'currency' => 'USD',
                    'qr_payload' => $bakongStatus === 'pending' ? 'DEMO-KHQR-'.$shopOrder->shop_order_number : null,
                    'md5' => md5('DEMO-KHQR-'.$shopOrder->shop_order_number),
                    'status' => $bakongStatus,
                    'transaction_hash' => $isPaid ? hash('sha256', $shopOrder->shop_order_number.'-capture') : null,
                    'expires_at' => $placedAt->copy()->addMinutes(15),
                    'paid_at' => $isPaid ? $placedAt->copy()->addMinutes(4) : null,
                ]);
            }
            if ($isPaid) {
                PaymentTransaction::create([
                    'payment_id' => $payment->id,
                    'type' => 'capture',
                    'status' => 'success',
                    'amount' => $order->total,
                    'reference' => $order->order_number.'-PAY',
                    'meta' => ['demo' => true, 'provider' => 'bakong_synthetic'],
                ]);
            }
        } elseif ($scenario['cod_collected']) {
            foreach ($shopOrderModels as $shopOrder) {
                PaymentTransaction::create([
                    'payment_id' => $payment->id,
                    'shop_order_id' => $shopOrder->id,
                    'type' => 'collection',
                    'status' => 'success',
                    'amount' => $shopOrder->total,
                    'reference' => 'COD-'.$payment->id.'-'.$shopOrder->id,
                    'meta' => ['demo' => true, 'provider' => 'cash_on_delivery', 'created_by' => $admin->id],
                ]);
            }
        }

        if ($scenario['return_status'] !== null && isset($createdItems[0])) {
            $item = $createdItems[0];
            $shopOrder = $shopOrderModels[$item->shop_id];
            $returnStatus = $scenario['return_status'];
            $refundCents = (int) round(max(100, ((float) $shopOrder->subtotal - (float) $shopOrder->discount_amount + (float) $shopOrder->tax_amount) * 100) / max(1, $item->quantity));
            $refundAmount = min((float) $order->total, $refundCents / 100);

            $return = OrderReturn::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'user_id' => $customer->id,
                'shop_id' => $item->shop_id,
                'quantity' => 1,
                'status' => $returnStatus,
                'reason' => match ($returnStatus) {
                    'requested' => 'Size or specification did not match what I needed.',
                    'approved' => 'Packaging arrived dented on one corner.',
                    'received' => 'Returning spare unit as agreed with seller.',
                    default => 'Synthetic demo return completed and refunded.',
                },
                'resolution_note' => $returnStatus === 'requested' ? null : 'Reviewed under synthetic demo return policy.',
                'photos' => [],
                'refund_amount' => $refundAmount,
                'restocked' => $returnStatus === 'received',
                'reviewed_by' => $returnStatus === 'requested' ? null : $admin->id,
                'created_at' => $placedAt->copy()->addDays(4),
                'updated_at' => $placedAt->copy()->addDays(5),
            ]);

            if ($returnStatus === 'refunded') {
                $bakongRow = $isBakong
                    ? BakongPayment::where('order_id', $order->id)->where('shop_id', $item->shop_id)->first()
                    : null;

                PaymentRefund::create([
                    'order_id' => $order->id,
                    'bakong_payment_id' => $bakongRow?->id,
                    'order_return_id' => $return->id,
                    'amount' => $refundAmount,
                    'currency' => 'USD',
                    'reference' => hash('sha256', $order->order_number.'-refund'),
                    'method' => $bakongRow ? 'bakong_verified' : 'cash_receipt',
                    'recorded_by' => $admin->id,
                    'created_at' => $placedAt->copy()->addDays(5),
                ]);

                if ($scenario['payment_status'] === 'partially_refunded') {
                    $order->update(['payment_status' => 'partially_refunded']);
                }
            }
        }
    }

    private function seedShopOperatingExpenses(User $admin): void
    {
        $shops = Shop::active()->where('code', 'not like', 'DEMO-FC-%')->orderBy('id')->take(10)->get();
        foreach ($shops as $idx => $shop) {
            foreach ([0, 1, 2] as $monthsAgo) {
                $date = now()->subMonths($monthsAgo)->startOfMonth()->addDays(4);
                $desc = self::SOURCE_NOTE.' '.$shop->code.' '.$date->format('Y-m').' store utilities & packaging';
                BusinessExpense::withTrashed()->firstOrCreate(
                    ['shop_id' => $shop->id, 'currency' => 'USD', 'description' => $desc],
                    [
                        'created_by' => $admin->id,
                        'incurred_on' => $date->toDateString(),
                        'category' => $idx % 2 === 0 ? 'packaging' : 'rent',
                        'amount' => 45.00 + ($idx * 12.50),
                    ]
                );
            }
        }
    }

    /**
     * 36 curated order fixtures covering every lifecycle state, payment method,
     * multi-shop allocation split, COD remittance, and return workflow stage.
     *
     * @return list<array<string, mixed>>
     */
    private function scenarioMatrix(): array
    {
        $rows = [
            // First 8 orders belong to Demo Customer (customer@ekhmer.com) for rich account history.
            ['label' => 'Pending Bakong QR checkout', 'order_status' => Order::STATUS_PENDING, 'payment_status' => Order::PAYMENT_UNPAID, 'method' => 'bakong', 'cod_collected' => false, 'multi_shop' => false, 'use_coupon' => false, 'return_status' => null, 'split_shipped_and_delivered' => false],
            ['label' => 'Confirmed multi-shop Bakong order', 'order_status' => Order::STATUS_CONFIRMED, 'payment_status' => Order::PAYMENT_PAID, 'method' => 'bakong', 'cod_collected' => false, 'multi_shop' => true, 'use_coupon' => true, 'return_status' => null, 'split_shipped_and_delivered' => false],
            ['label' => 'Processing COD order', 'order_status' => Order::STATUS_PROCESSING, 'payment_status' => Order::PAYMENT_UNPAID, 'method' => 'cod', 'cod_collected' => false, 'multi_shop' => false, 'use_coupon' => false, 'return_status' => null, 'split_shipped_and_delivered' => false],
            ['label' => 'Multi-shop shipped partial delivery', 'order_status' => Order::STATUS_SHIPPED, 'payment_status' => Order::PAYMENT_PAID, 'method' => 'bakong', 'cod_collected' => false, 'multi_shop' => true, 'use_coupon' => false, 'return_status' => null, 'split_shipped_and_delivered' => true],
            ['label' => 'Delivered COD awaiting cash collection', 'order_status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_UNPAID, 'method' => 'cod', 'cod_collected' => false, 'multi_shop' => false, 'use_coupon' => true, 'return_status' => null, 'split_shipped_and_delivered' => false],
            ['label' => 'Delivered COD collected & settled', 'order_status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_PAID, 'method' => 'cod', 'cod_collected' => true, 'multi_shop' => true, 'use_coupon' => false, 'return_status' => 'requested', 'split_shipped_and_delivered' => false],
            ['label' => 'Delivered Bakong with completed partial refund', 'order_status' => Order::STATUS_DELIVERED, 'payment_status' => 'partially_refunded', 'method' => 'bakong', 'cod_collected' => false, 'multi_shop' => false, 'use_coupon' => true, 'return_status' => 'refunded', 'split_shipped_and_delivered' => false],
            ['label' => 'Cancelled pre-dispatch COD order', 'order_status' => Order::STATUS_CANCELLED, 'payment_status' => Order::PAYMENT_FAILED, 'method' => 'cod', 'cod_collected' => false, 'multi_shop' => false, 'use_coupon' => false, 'return_status' => null, 'split_shipped_and_delivered' => false],
        ];

        $templates = [
            ['Delivered Bakong single-shop', Order::STATUS_DELIVERED, Order::PAYMENT_PAID, 'bakong', false, false, true, null, false],
            ['Delivered COD collected', Order::STATUS_DELIVERED, Order::PAYMENT_PAID, 'cod', true, false, false, null, false],
            ['Delivered multi-shop Bakong', Order::STATUS_DELIVERED, Order::PAYMENT_PAID, 'bakong', false, true, true, null, false],
            ['Delivered COD awaiting remittance', Order::STATUS_DELIVERED, Order::PAYMENT_UNPAID, 'cod', false, false, false, null, false],
            ['Delivered Bakong with approved return', Order::STATUS_DELIVERED, Order::PAYMENT_PAID, 'bakong', false, false, false, 'approved', false],
            ['Delivered COD with received return', Order::STATUS_DELIVERED, Order::PAYMENT_PAID, 'cod', true, false, false, 'received', false],
            ['Delivered Bakong with partial refund', Order::STATUS_DELIVERED, 'partially_refunded', 'bakong', false, false, true, 'refunded', false],
            ['Shipped tracked provincial parcel', Order::STATUS_SHIPPED, Order::PAYMENT_UNPAID, 'cod', false, false, false, null, false],
            ['Processing multi-shop order', Order::STATUS_PROCESSING, Order::PAYMENT_PAID, 'bakong', false, true, false, null, false],
            ['Confirmed COD order', Order::STATUS_CONFIRMED, Order::PAYMENT_UNPAID, 'cod', false, false, false, null, false],
            ['Pending COD checkout', Order::STATUS_PENDING, Order::PAYMENT_UNPAID, 'cod', false, false, false, null, false],
            ['Cancelled Bakong checkout', Order::STATUS_CANCELLED, Order::PAYMENT_FAILED, 'bakong', false, false, false, null, false],
        ];

        for ($i = 0; $i < 28; $i++) {
            [$label, $status, $payStatus, $method, $codCollected, $multi, $coupon, $ret, $split] = $templates[$i % count($templates)];
            $rows[] = [
                'label' => $label,
                'order_status' => $status,
                'payment_status' => $payStatus,
                'method' => $method,
                'cod_collected' => $codCollected,
                'multi_shop' => $multi,
                'use_coupon' => $coupon,
                'return_status' => $ret,
                'split_shipped_and_delivered' => $split,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        if ($total === 0) {
            $weights = array_fill_keys(array_keys($weights), 1);
            $total = count($weights);
        }

        $result = [];
        $cumulative = 0;
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            $through = (int) round($amount * $cumulative / $total);
            $result[$key] = $through - $allocated;
            $allocated = $through;
        }

        return $result;
    }
}
