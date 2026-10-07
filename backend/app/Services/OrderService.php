<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService
{
    public function __construct(
        private InventoryService $inventory,
        private CouponService $coupon,
        private ShopOrderFulfillmentService $fulfillment,
    ) {}

    /**
     * Paginated list of a user's orders, newest first.
     */
    public function listFor(User $user, ?string $status = null): LengthAwarePaginator
    {
        return $user->orders()
            ->with(['items.shop', 'payment', 'shipments', 'trackingEvents.changedBy', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents'])
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->latest('placed_at')
            ->paginate(10);
    }

    /**
     * Find an order by its number, scoped to the owning user.
     */
    public function findByNumber(User $user, string $orderNumber): Order
    {
        $order = $user->orders()->with(['items.shop', 'payment', 'shipments', 'trackingEvents.changedBy', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents'])->where('order_number', $orderNumber)->first();

        if ($order === null) {
            throw new NotFoundHttpException;
        }

        return $order;
    }

    /**
     * The strict order lifecycle state map.
     */
    public function transitions(): array
    {
        return [
            Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
            Order::STATUS_CONFIRMED => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED, Order::STATUS_REFUNDED],
            Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED, Order::STATUS_REFUNDED],
            Order::STATUS_DELIVERED => [Order::STATUS_REFUNDED],
        ];
    }

    /**
     * Transition an order through the strict state machine.
     */
    public function transition(Order $order, string $to, ?int $changedBy = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $changedBy, $note): Order {
            $order = Order::with(['items', 'payment'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $order->status;

            $allowed = $this->transitions()[$order->status] ?? [];

            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages(['message' => "Invalid status transition from {$order->status} to {$to}"]);
            }

            if ($from === Order::STATUS_PENDING && $to === Order::STATUS_CONFIRMED && $order->shopOrders()->exists()) {
                if ($order->payment?->method !== 'cod' && $order->payment_status !== Order::PAYMENT_PAID) {
                    throw ValidationException::withMessages(['status' => ['Online payment must be confirmed before this order can be fulfilled.']]);
                }
                $quantities = $order->items->whereNotNull('product_variant_id')->groupBy('product_variant_id')
                    ->map(fn ($items) => (int) $items->sum('quantity'))->all();
                $this->inventory->deductMany($quantities);
            }

            if ($to === Order::STATUS_SHIPPED) {
                $shipment = $order->shipments()->whereNull('shop_order_id')->first();
                if ($shipment !== null) {
                    $shipment->status = Shipment::STATUS_SHIPPED;
                    $shipment->shipped_at = now();
                    $shipment->save();
                }
            }

            if ($to === Order::STATUS_DELIVERED) {
                $shipment = $order->shipments()->whereNull('shop_order_id')->first();
                if ($shipment !== null) {
                    $shipment->status = Shipment::STATUS_DELIVERED;
                    $shipment->delivered_at = now();
                    $shipment->save();
                }
            }

            // Any terminal outcome (cancelled / refunded) must return the goods
            // to the pool and release coupon capacity so stock and usage are
            // never leaked.
            if ($to === Order::STATUS_CANCELLED || $to === Order::STATUS_REFUNDED) {
                if ($to === Order::STATUS_CANCELLED && ! $order->canBeCancelled()) {
                    throw ValidationException::withMessages(['status' => ['A delivery has already been dispatched. The whole order can no longer be cancelled.']]);
                }
                $this->revertFulfilment($order);
                $this->coupon->releaseUsage($order);
            }

            if ($to === Order::STATUS_REFUNDED) {
                $this->markPaymentRefunded($order);
            }

            if ($to === Order::STATUS_CANCELLED) {
                $this->markPaymentRefunded($order);
            }

            $order->status = $to;
            $order->save();
            $this->fulfillment->synchronizeAll($order, $to, $changedBy, $note);

            $this->recordTrackingEvent($order, $from, $to, $changedBy, $note);
            $this->notifyStatusChange($order, $to);

            return $order->load(['items.shop', 'payment', 'shipments', 'trackingEvents.changedBy', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents']);
        });
    }

    private function recordTrackingEvent(Order $order, ?string $from, string $to, ?int $changedBy = null, ?string $note = null): void
    {
        $descriptions = [
            Order::STATUS_CONFIRMED => 'Order confirmed',
            Order::STATUS_PROCESSING => 'Order is being processed',
            Order::STATUS_SHIPPED => 'Order has been shipped',
            Order::STATUS_DELIVERED => 'Order has been delivered',
            Order::STATUS_CANCELLED => 'Order was cancelled',
            Order::STATUS_REFUNDED => 'Order was refunded',
        ];

        $order->trackingEvents()->create([
            'from_status' => $from,
            'status' => $to,
            'description' => $note ?: ($descriptions[$to] ?? 'Status changed to '.$to),
            'changed_by' => $changedBy,
        ]);
    }

    private function notifyStatusChange(Order $order, string $to): void
    {
        $statusesWithNotifications = [
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
            Order::STATUS_REFUNDED,
        ];

        if ($order->user_id === null || ! in_array($to, $statusesWithNotifications, true)) {
            return;
        }

        $order->user()->first()?->notify(new OrderStatusNotification($order, $to));
    }

    /**
     * Reverse the effect fulfilment had on inventory.
     *
     * Orders that never left `pending` only hold reservations, so they are
     * released. Orders that were confirmed have already been deducted at
     * payment time, so their quantity and sold-count are restored instead.
     */
    private function revertFulfilment(Order $order): void
    {
        $items = [];
        foreach ($order->items as $item) {
            if ($item->product_variant_id === null) {
                continue;
            }
            $items[(int) $item->product_variant_id] = (int) $item->quantity;
        }

        if ($order->status === Order::STATUS_PENDING) {
            $this->inventory->releaseMany($items);

            return;
        }

        $this->inventory->restockMany($items);
    }

    /**
     * Mark an order's payment as refunded, but only if money was actually taken.
     */
    private function markPaymentRefunded(Order $order): void
    {
        $payment = $order->payment;

        if ($payment === null || $order->payment_status !== Order::PAYMENT_PAID) {
            return;
        }

        if ($payment->status !== Payment::STATUS_REFUNDED) {
            $payment->status = Payment::STATUS_REFUNDED;
            $payment->save();

            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'type' => 'refund',
                'status' => 'success',
                'amount' => round((float) $payment->amount, 2),
                'reference' => $payment->transaction_id,
            ]);
        }

        $order->payment_status = Order::PAYMENT_REFUNDED;
    }

    /**
     * Cancel an order by the customer or admin, releasing active reservations,
     * refunding paid orders and restoring any deducted stock.
     */
    public function cancelOwn(Order $order, ?int $changedBy = null): Order
    {
        return DB::transaction(function () use ($order, $changedBy): Order {
            $order = Order::with(['items', 'payment'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $order->status;

            $allowed = [Order::STATUS_PENDING, Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING];

            if (! in_array($order->status, $allowed, true) || ! $order->canBeCancelled()) {
                throw ValidationException::withMessages(['message' => 'Order cannot be cancelled in its current state']);
            }

            $this->revertFulfilment($order);
            $this->markPaymentRefunded($order);
            $this->coupon->releaseUsage($order);

            $order->status = Order::STATUS_CANCELLED;
            $order->note = trim(($order->note ? $order->note.' ' : '').'cancelled');
            $order->save();
            $this->fulfillment->synchronizeAll($order, Order::STATUS_CANCELLED, $changedBy);

            $this->recordTrackingEvent($order, $from, Order::STATUS_CANCELLED, $changedBy);
            $this->notifyStatusChange($order, Order::STATUS_CANCELLED);

            return $order->load(['items.shop', 'payment', 'shipments', 'trackingEvents.changedBy', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents']);
        });
    }
}
