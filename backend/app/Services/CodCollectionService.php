<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CodCollectionService
{
    public function collect(ShopOrder $allocation, User $actor): ShopOrder
    {
        return DB::transaction(function () use ($allocation, $actor): ShopOrder {
            // All payment, fulfillment and refund operations lock the parent first.
            // This serializes both duplicate requests and collections for sibling shops.
            $order = Order::whereKey($allocation->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $allocation = ShopOrder::with(['shop', 'shipment', 'codCollection'])->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isSuperAdmin() || ($allocation->shop?->isActive() && $actor->ownsShop($allocation->shop)), 403, 'You do not have permission to manage this payment.');
            $order->setRelation('payment', $payment);
            $allocation->setRelation('order', $order);

            if ($payment->method !== 'cod') {
                throw ValidationException::withMessages(['payment' => 'Only cash-on-delivery payments can use this action.']);
            }
            // A lost response may be retried even after a later refund. Never create
            // another collection or move a terminal financial state back to paid.
            if ($allocation->codCollection !== null) {
                return $allocation;
            }
            if (! $allocation->canCollectCod()) {
                throw ValidationException::withMessages(['payment' => 'Cash can only be collected for a delivered, unpaid COD shop order that has not been cancelled or refunded.']);
            }
            $amount = (int) round((float) $allocation->total * 100);
            $collected = (int) round((float) $payment->transactions()->where('type', 'collection')->where('status', 'success')->sum('amount') * 100);
            if ($collected + $amount > (int) round((float) $payment->amount * 100)) {
                throw ValidationException::withMessages(['payment' => 'The collection exceeds the saved payment balance. Contact support.']);
            }

            PaymentTransaction::create([
                'payment_id' => $payment->id, 'shop_order_id' => $allocation->id,
                'type' => 'collection', 'status' => 'success', 'amount' => $amount / 100,
                'reference' => 'COD-'.$payment->id.'-'.$allocation->id,
                'meta' => ['provider' => 'cash_on_delivery', 'created_by' => $actor->id, 'actor_name' => $actor->name,
                    'actor_role' => $actor->isSuperAdmin() ? 'super_admin' : 'shop_management', 'shop_id' => $allocation->shop_id],
            ]);
            $this->synchronize($order, $payment);

            return $allocation->load('codCollection');
        }, 3);
    }

    /** Caller holds the order lock; refunds and collections share this aggregation. */
    public function synchronize(Order $order, ?Payment $payment = null): void
    {
        $payment ??= $order->payment;
        $collected = (int) round((float) $payment->transactions()->where('type', 'collection')->where('status', 'success')->sum('amount') * 100);
        $refunded = (int) round((float) PaymentRefund::where('order_id', $order->id)->sum('amount') * 100);
        $allCollected = ! $order->shopOrders()->whereDoesntHave('codCollection')->exists()
            && $collected === (int) round((float) $payment->amount * 100);
        $status = $allCollected ? ($refunded > 0 ? ($refunded >= $collected ? 'refunded' : 'partially_refunded') : 'paid') : 'partial';
        $order->update(['payment_status' => $status]);
        $payment->update(['status' => $status === 'refunded' ? Payment::STATUS_REFUNDED : ($allCollected ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING),
            'paid_at' => $allCollected ? ($payment->paid_at ?? now()) : null]);
    }
}
