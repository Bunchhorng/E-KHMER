<?php

namespace Database\Seeders;

use App\Models\BakongPayment;
use App\Models\CheckoutAttempt;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('email', 'customer@ekhmer.dev')->firstOrFail();
        $admin = User::where('email', 'admin@ekhmer.dev')->firstOrFail();
        $address = $customer->addresses()->where('is_default', true)->firstOrFail();
        $snapshot = $address->only([
            'full_name', 'phone', 'address_line1', 'address_line2',
            'city', 'state', 'postal_code', 'country',
        ]);
        $coupons = Coupon::orderBy('id')->get();
        $methods = ShippingMethod::get()->keyBy('shop_id');

        foreach (ProductVariant::with(['product', 'image'])->orderBy('id')->get() as $index => $variant) {
            $coupon = $coupons[$index];
            $method = $methods[$variant->shop_id];
            $subtotal = round((float) $variant->price * 2, 2);
            $discount = round($subtotal * (float) $coupon->value / 100, 2);
            $tax = round(($subtotal - $discount) * 0.10, 2);
            $total = round($subtotal - $discount + $tax + (float) $method->price, 2);
            $placedAt = now()->subDays(10 + $index % 7);
            $paidAt = $placedAt->copy()->addMinutes(4);
            $shippedAt = $placedAt->copy()->addDay();
            $deliveredAt = $shippedAt->copy()->addDays(2);
            $refundAt = $deliveredAt->copy()->addDays(2);
            $number = 'DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            // All twenty purchases are delivered so each has a valid review and return.
            $order = Order::create([
                'order_number' => $number, 'user_id' => $customer->id,
                'coupon_id' => $coupon->id, 'coupon_code' => $coupon->code,
                'status' => Order::STATUS_DELIVERED, 'payment_status' => 'partially_refunded',
                'subtotal' => $subtotal, 'discount_amount' => $discount,
                'tax_amount' => $tax, 'shipping_amount' => $method->price, 'total' => $total,
                'currency' => 'USD', 'shipping_address' => $snapshot, 'billing_address' => $snapshot,
                'email' => $customer->email, 'phone' => $customer->phone,
                'customer_name' => $customer->name, 'placed_at' => $placedAt,
                'note' => 'Demo purchase: one of two units returned without restocking.',
            ]);
            $shopOrder = ShopOrder::create([
                'order_id' => $order->id, 'shop_id' => $variant->shop_id,
                'shop_order_number' => $number.'-1', 'status' => $order->status,
                'subtotal' => $subtotal, 'discount_amount' => $discount,
                'tax_amount' => $tax, 'shipping_amount' => $method->price, 'total' => $total,
            ]);
            $item = OrderItem::create([
                'order_id' => $order->id, 'shop_order_id' => $shopOrder->id,
                'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
                'shop_id' => $variant->shop_id, 'product_name' => $variant->product->name,
                'variant_label' => $variant->name, 'sku' => $variant->sku,
                'image_path' => $variant->image->image_path,
                'unit_price' => $variant->price, 'quantity' => 2, 'line_total' => $subtotal,
            ]);
            CouponUsage::create([
                'coupon_id' => $coupon->id, 'user_id' => $customer->id,
                'order_id' => $order->id, 'redeemed_at' => $placedAt,
            ]);
            $coupon->increment('used_count');
            $payment = Payment::create([
                'order_id' => $order->id, 'method' => 'bakong',
                'status' => Payment::STATUS_COMPLETED, 'transaction_id' => $number.'-PAY',
                'amount' => $total, 'paid_at' => $paidAt, 'provider_data' => ['demo' => true],
            ]);
            PaymentTransaction::create([
                'payment_id' => $payment->id, 'type' => 'capture', 'status' => 'success',
                'amount' => $total, 'reference' => $payment->transaction_id, 'meta' => ['demo' => true],
            ]);
            Shipment::create([
                'order_id' => $order->id, 'shop_order_id' => $shopOrder->id,
                'shipping_method_id' => $method->id, 'tracking_number' => $number.'-SHIP',
                'carrier' => 'Demo Delivery', 'status' => Shipment::STATUS_DELIVERED,
                'address_snapshot' => $snapshot, 'shipped_at' => $shippedAt, 'delivered_at' => $deliveredAt,
            ]);
            $shopOrder->trackingEvents()->create([
                'order_id' => $order->id, 'from_status' => Order::STATUS_SHIPPED,
                'status' => Order::STATUS_DELIVERED, 'description' => 'Demo delivery completed.',
                'changed_by' => $admin->id, 'created_at' => $deliveredAt,
            ]);

            // Historical fixtures only: never generate QR codes or call a payment gateway.
            $bakong = BakongPayment::create([
                'order_id' => $order->id, 'shop_order_id' => $shopOrder->id, 'shop_id' => $variant->shop_id,
                'receiver_account' => 'demo-shop@example.invalid', 'payer_account' => 'demo-payer@example.invalid',
                'amount' => $total, 'currency' => 'USD', 'status' => 'paid',
                'transaction_hash' => hash('sha256', $number.'-capture'),
                'expires_at' => $placedAt->copy()->addMinutes(15), 'paid_at' => $paidAt,
            ]);
            $refundAmount = round(($subtotal - $discount + $tax) / $item->quantity, 2);
            $return = OrderReturn::create([
                'order_id' => $order->id, 'order_item_id' => $item->id,
                'user_id' => $customer->id, 'shop_id' => $variant->shop_id,
                'quantity' => 1, 'status' => 'refunded', 'reason' => 'One spare item returned.',
                'resolution_note' => 'Demo refund recorded; returned item is not suitable for restocking.',
                'photos' => [], 'refund_amount' => $refundAmount, 'restocked' => false,
                'reviewed_by' => $admin->id, 'created_at' => $deliveredAt->copy()->addDay(), 'updated_at' => $refundAt,
            ]);
            PaymentRefund::create([
                'order_id' => $order->id, 'bakong_payment_id' => $bakong->id, 'order_return_id' => $return->id,
                'amount' => $refundAmount, 'currency' => 'USD', 'reference' => hash('sha256', $number.'-refund'),
                'method' => 'bakong_verified', 'recorded_by' => $admin->id, 'created_at' => $refundAt,
            ]);
            CheckoutAttempt::create([
                'owner_key' => hash('sha256', 'user:'.$customer->id), 'request_key' => 'demo-checkout-'.$number,
                'payload_hash' => hash('sha256', $number), 'order_id' => $order->id,
            ]);
        }
    }
}
