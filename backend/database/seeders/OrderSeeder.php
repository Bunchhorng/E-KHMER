<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\ShopOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Orders across every lifecycle state with snapshot items, payments, and shipments.
     */
    public function run(): void
    {
        foreach ($this->orders() as $spec) {
            $this->createOrder($spec);
        }
    }

    private function createOrder(array $spec): void
    {
        DB::transaction(function () use ($spec) {
            $user = User::where('email', 'customer@ekhmer.dev')->firstOrFail();
            $address = $user->addresses()->where('is_default', true)->firstOrFail();

            $method = ShippingMethod::where('code', $spec['shipping'])->firstOrFail();

            $lines = [];
            foreach ($spec['items'] as $sku => $qty) {
                $variant = ProductVariant::where('sku', $sku)->firstOrFail();
                $product = Product::findOrFail($variant->product_id);
                $unitPrice = ($variant->price ?? $product->price) ?: 0;
                $lines[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'shop_id' => $product->shop_id,
                    'product_name' => $product->name,
                    'variant_label' => $variant->name,
                    'sku' => $variant->sku,
                    'image_path' => $product->images()->where('is_cover', true)->value('image_path'),
                    'unit_price' => $unitPrice,
                    'quantity' => $qty,
                    'line_total' => round((float) $unitPrice * $qty, 2),
                ];
            }

            $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);

            $coupon = null;
            $discount = 0.0;
            if ($spec['coupon'] !== null) {
                $coupon = Coupon::where('code', $spec['coupon'])->first();
                if ($coupon && (float) $subtotal >= (float) $coupon->min_order_amount) {
                    $discount = $coupon->type === 'percentage'
                        ? round((float) $subtotal * ((float) $coupon->value / 100), 2)
                        : min((float) $coupon->value, (float) $subtotal);
                    $discount = round($discount, 2);
                }
            }

            $shipping = (float) $method->price;
            $tax = round(((float) $subtotal - $discount) * 0.10, 2);
            $total = round((float) $subtotal - $discount + $tax + $shipping, 2);

            $placedAt = Carbon::now()->subDays($spec['days_ago'])->setTime(10 + $spec['days_ago'] % 8, 15);

            $shipAddress = [
                'full_name' => $address->full_name,
                'phone' => $address->phone,
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
            ];

            $order = Order::create([
                'order_number' => 'SV-2026-10'.str_pad((string) (100 + $spec['seq']), 3, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'status' => $spec['status'],
                'payment_status' => $spec['payment_status'],
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'shipping_amount' => $shipping,
                'total' => $total,
                'currency' => 'USD',
                'shipping_address' => $shipAddress,
                'billing_address' => $shipAddress,
                'email' => $user->email,
                'phone' => $address->phone,
                'customer_name' => $user->name,
                'note' => $spec['note'] ?? null,
                'placed_at' => $placedAt,
            ]);

            foreach ($lines as $line) {
                OrderItem::create($line + ['order_id' => $order->id]);
            }

            app(ShopOrderService::class)->split($order);
            $order->shopOrders()->update(['status' => $spec['status']]);
            $order->trackingEvents()->create([
                'status' => Order::STATUS_PENDING,
                'description' => 'Order placed',
                'created_at' => $placedAt,
            ]);

            if ($coupon !== null && $discount > 0) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'redeemed_at' => $placedAt,
                ]);
                $coupon->increment('used_count');
            }

            if ($spec['payment_status'] !== Order::PAYMENT_UNPAID) {
                $paymentStatus = $spec['status'] === Order::STATUS_REFUNDED ? Payment::STATUS_REFUNDED : Payment::STATUS_COMPLETED;
                $paidAt = $spec['status'] === Order::STATUS_REFUNDED ? $placedAt : $placedAt->copy()->addMinutes(4);
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'method' => $spec['payment_method'] ?? 'card',
                    'status' => $paymentStatus,
                    'transaction_id' => 'PAY-'.Str::upper(Str::random(12)),
                    'amount' => $total,
                    'paid_at' => $paymentStatus === Payment::STATUS_COMPLETED ? $paidAt : null,
                ]);

                PaymentTransaction::create([
                    'payment_id' => $payment->id,
                    'type' => 'capture',
                    'status' => 'success',
                    'amount' => $total,
                    'reference' => $payment->transaction_id,
                    'meta' => ['settled_at' => (string) $paidAt],
                ]);

                if ($spec['status'] === Order::STATUS_REFUNDED) {
                    PaymentTransaction::create([
                        'payment_id' => $payment->id,
                        'type' => 'refund',
                        'status' => 'success',
                        'amount' => $total,
                        'reference' => 'REF-'.Str::upper(Str::random(10)),
                        'meta' => ['full' => true],
                    ]);
                }
            }

            if (in_array($spec['status'], [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_REFUNDED], true)) {
                $shipStatus = $spec['status'] === Order::STATUS_DELIVERED ? Shipment::STATUS_DELIVERED : Shipment::STATUS_SHIPPED;
                $shippedAt = $placedAt->copy()->addDays(max($spec['days_ago'] >= 7 ? 1 : 0, 1));
                Shipment::create([
                    'order_id' => $order->id,
                    'shipping_method_id' => $method->id,
                    'tracking_number' => '1Z'.Str::upper(Str::random(16)),
                    'carrier' => 'SwiftPost',
                    'status' => $shipStatus,
                    'address_snapshot' => $shipAddress,
                    'shipped_at' => $shippedAt,
                    'delivered_at' => $shipStatus === Shipment::STATUS_DELIVERED ? $shippedAt->copy()->addDays(3) : null,
                ]);
            }
        });
    }

    /**
     * @return list<array{
     *     seq: int, email: string, status: string, payment_status: string, shipping: string,
     *     coupon: string|null, days_ago: int, items: array<string, int>,
     *     payment_method?: string, note?: string
     * }>
     */
    private function orders(): array
    {
        return [
            ['seq' => 1, 'email' => 'olivia.bennett@example.com', 'status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'express', 'coupon' => 'WELCOME10', 'days_ago' => 14, 'items' => ['aurora-black' => 1, 'orbit-ocean' => 1]],
            ['seq' => 2, 'email' => 'jake.miller@example.com', 'status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'standard', 'coupon' => 'SAVE20', 'days_ago' => 11, 'items' => ['aerolite-wh-43' => 1, 'stride-g-10' => 1]],
            ['seq' => 3, 'email' => 'marcus.lee@example.com', 'status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'standard', 'coupon' => 'FREESHIP', 'days_ago' => 18, 'items' => ['meridian-navy-m' => 1, 'nova-white-l' => 1]],
            ['seq' => 4, 'email' => 'elena.rodriguez@example.com', 'status' => Order::STATUS_DELIVERED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'standard', 'coupon' => 'WELCOME10', 'days_ago' => 9, 'items' => ['lumiere-30ml' => 2]],
            ['seq' => 5, 'email' => 'olivia.bennett@example.com', 'status' => Order::STATUS_SHIPPED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'express', 'coupon' => null, 'days_ago' => 5, 'items' => ['pulse-rose' => 1, 'serene-oak' => 1]],
            ['seq' => 6, 'email' => 'marcus.lee@example.com', 'status' => Order::STATUS_SHIPPED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'standard', 'coupon' => null, 'days_ago' => 4, 'items' => ['cascade-tan' => 1, 'ruby-01' => 2]],
            ['seq' => 7, 'email' => 'priya.shah@example.com', 'status' => Order::STATUS_PROCESSING, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'standard', 'coupon' => 'WELCOME10', 'days_ago' => 2, 'items' => ['nova-beige-m' => 1, 'nova-beige-s' => 1, 'lumiere-30ml' => 1]],
            ['seq' => 8, 'email' => 'dan.okafor@example.com', 'status' => Order::STATUS_PROCESSING, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'express', 'coupon' => null, 'days_ago' => 3, 'items' => ['aerolite-blk-42' => 1]],
            ['seq' => 9, 'email' => 'jake.miller@example.com', 'status' => Order::STATUS_CONFIRMED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'standard', 'coupon' => 'SAVE20', 'days_ago' => 1, 'items' => ['stride-w-9' => 1, 'orbit-black' => 1]],
            ['seq' => 10, 'email' => 'elena.rodriguez@example.com', 'status' => Order::STATUS_CONFIRMED, 'payment_status' => Order::PAYMENT_PAID, 'shipping' => 'standard', 'coupon' => null, 'days_ago' => 1, 'items' => ['vertex-base' => 1]],
            ['seq' => 11, 'email' => 'priya.shah@example.com', 'status' => Order::STATUS_PENDING, 'payment_status' => Order::PAYMENT_UNPAID, 'shipping' => 'standard', 'coupon' => null, 'days_ago' => 0, 'items' => ['aurora-white' => 1], 'note' => 'Please leave in mailbox.'],
            ['seq' => 12, 'email' => 'dan.okafor@example.com', 'status' => Order::STATUS_PENDING, 'payment_status' => Order::PAYMENT_UNPAID, 'shipping' => 'standard', 'coupon' => null, 'days_ago' => 0, 'items' => ['meridian-grey-l' => 1]],
            ['seq' => 13, 'email' => 'olivia.bennett@example.com', 'status' => Order::STATUS_CANCELLED, 'payment_status' => Order::PAYMENT_UNPAID, 'shipping' => 'standard', 'coupon' => null, 'days_ago' => 3, 'items' => ['meridian-navy-m' => 2], 'payment_method' => 'cod', 'note' => 'Customer cancelled before dispatch.'],
            ['seq' => 14, 'email' => 'marcus.lee@example.com', 'status' => Order::STATUS_REFUNDED, 'payment_status' => Order::PAYMENT_REFUNDED, 'shipping' => 'standard', 'coupon' => 'WELCOME10', 'days_ago' => 12, 'items' => ['orbit-ocean' => 1, 'cascade-tan' => 1]],
        ];
    }
}
