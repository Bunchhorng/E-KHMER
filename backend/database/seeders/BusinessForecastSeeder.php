<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessExpense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Analytics\BusinessForecastService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BusinessForecastSeeder extends Seeder
{
    use WithoutModelEvents;

    public const SOURCE = 'BusinessForecastSeeder demo';

    private const SCENARIOS = [
        'GROWTH' => ['name' => 'Demo Forecast - Growing Shop', 'price' => 2500, 'cost' => 1150, 'rent' => 9000],
        'STEADY' => ['name' => 'Demo Forecast - Steady Shop', 'price' => 3500, 'cost' => 2100, 'rent' => 11000],
        'DECLINE' => ['name' => 'Demo Forecast - Declining Shop', 'price' => 3200, 'cost' => 2000, 'rent' => 18000],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Forecast demo data may only be seeded in local or testing environments.');
        }
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->orderBy('id')->first();
        if ($admin === null) {
            throw new RuntimeException('Create a Super Admin or run UserSeeder before seeding forecast demo data.');
        }
        $now = now()->copy();
        $start = $now->copy()->subYears(4)->startOfYear();
        $currentMonth = $now->copy()->startOfMonth();
        $seller = User::where('email', 'seller@ekhmer.com')->where('role', User::ROLE_CUSTOMER)->first();

        DB::transaction(function () use ($admin, $seller, $now, $start, $currentMonth): void {
            $buyer = User::firstOrCreate(['email' => 'demo.forecast.buyer@example.invalid'], [
                'name' => 'Demo Forecast Customer', 'password' => Str::password(48),
                'newsletter' => false, 'created_at' => $start,
            ]);
            foreach (self::SCENARIOS as $scenario => $profile) {
                $shop = Shop::withTrashed()->firstOrCreate(['code' => 'DEMO-FC-'.$scenario], [
                    'name' => $profile['name'], 'slug' => 'demo-forecast-'.strtolower($scenario),
                    'description' => self::SOURCE.': synthetic analytics examples, not real trading history.',
                    'status' => Shop::STATUS_ACTIVE, 'is_default' => false, 'branch_type' => 'storefront',
                    'city' => 'Phnom Penh', 'country' => 'KH', 'commission_rate' => 0, 'created_at' => $start,
                ]);
                if (! str_starts_with((string) $shop->description, self::SOURCE)) {
                    throw new RuntimeException('The reserved forecast demo shop code belongs to an unrelated shop: '.$shop->code);
                }
                if ($shop->trashed()) {
                    continue;
                }
                foreach (array_filter([$admin, $seller]) as $manager) {
                    if (! $shop->users()->whereKey($manager->id)->exists()) {
                        $shop->users()->attach($manager->id, ['role_in_shop' => $manager->id === $admin->id ? 'owner' : 'manager', 'status' => 'active']);
                    }
                }
                $index = 0;
                for ($month = $start->copy(); $month->lte($currentMonth); $month->addMonth(), $index++) {
                    $current = $month->equalTo($currentMonth);
                    $orderCount = $current ? 1 : 2;
                    for ($sale = 1; $sale <= $orderCount; $sale++) {
                        $this->sale($shop, $buyer, $admin, $scenario, $profile, $month->copy(), $index, $sale, $now);
                    }
                    $rent = $profile['rent'] + ($scenario === 'DECLINE' ? $index * 200 : 0);
                    $this->expense($shop, $admin, $month, 'rent', $rent);
                    $this->expense($shop, $admin, $month, 'shipping', $orderCount * 600);
                }
                $lastMonth = $currentMonth->copy()->subMonth();
                $this->sale($shop, $buyer, $admin, $scenario, $profile, $lastMonth, $index - 2, 3, $now, 'cancelled');
                $this->sale($shop, $buyer, $admin, $scenario, $profile, $lastMonth, $index - 2, 4, $now, 'failed');
            }
            DB::afterCommit(static fn () => BusinessForecastService::invalidate());
        });

        $this->command?->info('Forecast demo history seeded from '.$start->toDateString().' through '.$now->toDateString().'.');
        $this->command?->info('In Analytics, use Find a shop to search for Demo Forecast, then select a growing, steady, or declining shop.');
        $this->command?->info('For yearly predictions, set From to '.$start->toDateString().'. Existing data and logins were preserved.');
    }

    private function sale(Shop $shop, User $buyer, User $admin, string $scenario, array $profile, Carbon $month, int $index, int $sequence, Carbon $now, string $kind = 'sale'): void
    {
        $current = $month->isSameMonth($now);
        $quantity = match ($scenario) {
            'GROWTH' => 6 + intdiv($index, 2),
            'DECLINE' => max(5, 35 - intdiv($index, 2)),
            default => 10,
        };
        $quantity += $sequence === 2 ? 1 : 0;
        if ($current) {
            $quantity = max(1, (int) floor($quantity * $now->day / $now->daysInMonth));
        }
        $cost = $profile['cost'] + match ($scenario) {
            'GROWTH' => $index * 2,
            'DECLINE' => $index * 4,
            default => 0,
        };
        $fullRefund = $kind === 'sale' && $scenario === 'DECLINE' && $sequence === 1 && $month->isSameMonth($now->copy()->subMonthNoOverflow());
        $partialRefund = $kind === 'sale' && ! $current && $sequence === 1 && $index % 6 === 5 && ! $fullRefund;
        $number = 'DEMO-FC-'.$scenario.'-'.$month->format('Ym').'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        $placedAt = $month->copy()->day($current ? min(5, $now->day) : ($sequence === 2 ? 17 : 5))->startOfDay();
        $paidAt = $placedAt->copy()->addMinutes(5)->min($now);
        $deliveredAt = $placedAt->copy()->addDays(2)->min($now);
        $refundAt = $deliveredAt->copy()->addDays(2)->min($now);
        $subtotal = $profile['price'] * $quantity;
        $discount = (int) round($subtotal * .05);
        $tax = (int) round(($subtotal - $discount) * .10);
        $shipping = $fullRefund ? 0 : 450;
        $total = $subtotal - $discount + $tax + $shipping;
        $paymentState = match ($kind) {
            'cancelled' => 'unpaid', 'failed' => 'failed',
            default => $fullRefund ? 'refunded' : ($partialRefund ? 'partially_refunded' : 'paid'),
        };
        $address = ['full_name' => $buyer->name, 'address_line1' => 'Demo Forecast Street', 'city' => 'Phnom Penh', 'country' => 'KH'];
        $order = Order::withTrashed()->firstOrCreate(['order_number' => $number], [
            'user_id' => $buyer->id, 'customer_name' => $buyer->name, 'email' => $buyer->email,
            'shipping_address' => $address, 'billing_address' => $address,
            'status' => $kind === 'cancelled' ? 'cancelled' : ($kind === 'failed' ? 'confirmed' : 'delivered'),
            'payment_status' => $paymentState, 'subtotal' => $subtotal / 100, 'discount_amount' => $discount / 100,
            'tax_amount' => $tax / 100, 'shipping_amount' => $shipping / 100, 'total' => $total / 100,
            'currency' => 'USD', 'placed_at' => $placedAt, 'note' => self::SOURCE, 'created_at' => $placedAt,
        ]);
        if ($order->note !== self::SOURCE) {
            throw new RuntimeException('The reserved forecast demo order number belongs to an unrelated order: '.$number);
        }
        if ($order->trashed() || ! $order->wasRecentlyCreated) {
            return;
        }
        $allocation = ShopOrder::firstOrCreate(['order_id' => $order->id, 'shop_id' => $shop->id], [
            'shop_order_number' => $number.'-1', 'status' => $order->status,
            ...$order->only(['subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total']), 'created_at' => $placedAt,
        ]);
        $item = OrderItem::firstOrCreate(['order_id' => $order->id, 'shop_order_id' => $allocation->id], [
            'shop_id' => $shop->id, 'product_id' => null, 'product_variant_id' => null,
            'product_name' => '[DEMO] Historical merchandise - '.strtolower($scenario),
            'variant_label' => 'Historical sales sample', 'sku' => 'DEMO-FC-'.$scenario,
            'unit_price' => $profile['price'] / 100, 'unit_cost' => $cost / 100,
            'quantity' => $quantity, 'line_total' => $subtotal / 100, 'created_at' => $placedAt,
        ]);
        $settled = $kind === 'sale';
        $payment = Payment::firstOrCreate(['order_id' => $order->id], [
            'method' => 'cod', 'status' => $settled ? ($fullRefund ? 'refunded' : 'completed') : ($kind === 'failed' ? 'failed' : 'pending'),
            'transaction_id' => $number.'-PAY', 'amount' => $order->total,
            'paid_at' => $settled ? $paidAt : null, 'provider_data' => ['demo' => true, 'source' => self::SOURCE],
            'created_at' => $placedAt,
        ]);
        if (! $settled) {
            return;
        }
        PaymentTransaction::firstOrCreate(['payment_id' => $payment->id, 'reference' => $number.'-PAY'], [
            'type' => 'capture', 'status' => 'success', 'amount' => $payment->amount,
            'meta' => ['demo' => true], 'created_at' => $paidAt,
        ]);
        Shipment::firstOrCreate(['order_id' => $order->id, 'shop_order_id' => $allocation->id], [
            'carrier' => 'Demo Forecast Delivery', 'tracking_number' => $number.'-SHIP', 'status' => 'delivered',
            'address_snapshot' => $address, 'shipped_at' => $paidAt, 'delivered_at' => $deliveredAt, 'created_at' => $paidAt,
        ]);
        if ($fullRefund || $partialRefund) {
            $refundQuantity = $fullRefund ? $quantity : 1;
            $refundAmount = (int) round(($subtotal - $discount + $tax) * $refundQuantity / $quantity);
            $return = OrderReturn::firstOrCreate(['order_item_id' => $item->id, 'reason' => self::SOURCE], [
                'order_id' => $order->id, 'user_id' => $buyer->id, 'shop_id' => $shop->id,
                'quantity' => $refundQuantity, 'status' => 'refunded', 'refund_amount' => $refundAmount / 100,
                'restocked' => false, 'photos' => [], 'resolution_note' => 'Synthetic damaged-item return; no live inventory was changed.',
                'reviewed_by' => $admin->id, 'created_at' => $refundAt,
            ]);
            PaymentRefund::firstOrCreate(['reference' => $number.'-REFUND'], [
                'order_id' => $order->id, 'order_return_id' => $return->id, 'amount' => $return->refund_amount,
                'currency' => 'USD', 'method' => 'cash_receipt', 'recorded_by' => $admin->id, 'created_at' => $refundAt,
            ]);
            PaymentTransaction::firstOrCreate(['payment_id' => $payment->id, 'reference' => $number.'-REFUND'], [
                'type' => 'refund', 'status' => 'success', 'amount' => $return->refund_amount,
                'meta' => ['demo' => true], 'created_at' => $refundAt,
            ]);
        }
    }

    private function expense(Shop $shop, User $admin, Carbon $month, string $category, int $amount): void
    {
        BusinessExpense::withTrashed()->firstOrCreate([
            'shop_id' => $shop->id, 'currency' => 'USD',
            'description' => self::SOURCE.' '.$month->format('Y-m').' '.$category,
        ], [
            'created_by' => $admin->id, 'category' => $category, 'amount' => $amount / 100,
            'incurred_on' => $month->toDateString(), 'created_at' => $month,
        ]);
    }
}
