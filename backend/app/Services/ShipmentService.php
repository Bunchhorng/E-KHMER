<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function __construct(private OrderService $orders, private ShopOrderFulfillmentService $fulfillment) {}

    /**
     * Update shipment metadata and move it through the supported logistics
     * lifecycle. Shipment status is deliberately separate from order status,
     * but a shipment leaving/delivering synchronizes the corresponding order
     * transition when it is valid to do so.
     */
    public function update(Shipment $shipment, array $data, ?int $actorId = null): Shipment
    {
        if ($shipment->shop_order_id !== null) {
            $allocation = $this->fulfillment->updateShipment($shipment->shopOrder()->firstOrFail(), $data, $actorId);

            return $allocation->shipment->load(['order.user', 'method', 'shopOrder.shop']);
        }

        return DB::transaction(function () use ($shipment, $data, $actorId): Shipment {
            $order = Order::whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $shipment->setRelation('order', $order);
            $from = $shipment->status;
            $to = $data['status'];

            if ($from !== $to && ! in_array($to, $this->transitions()[$from] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Invalid shipment status transition from {$from} to {$to}."],
                ]);
            }

            $order = $shipment->order;
            if ($order === null || in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['A cancelled or refunded order cannot be shipped.'],
                ]);
            }

            if (in_array($to, [Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT], true)) {
                if ($order->shopOrders()->exists()) {
                    if (! in_array($order->status, [Order::STATUS_CONFIRMED, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED], true)) {
                        throw ValidationException::withMessages(['status' => ['Confirm the order before dispatching its shipment.']]);
                    }
                } elseif ($order->status === Order::STATUS_PROCESSING) {
                    $this->orders->transition($order, Order::STATUS_SHIPPED, $actorId, 'Shipment dispatched');
                } elseif (! in_array($order->status, [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED], true)) {
                    throw ValidationException::withMessages([
                        'status' => ['Move the order to processing before dispatching its shipment.'],
                    ]);
                }
            }

            if ($to === Shipment::STATUS_DELIVERED) {
                if ($order->shopOrders()->exists()) {
                    if (! in_array($from, [Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED], true)) {
                        throw ValidationException::withMessages(['status' => ['Only a dispatched shipment can be delivered.']]);
                    }
                } elseif ($order->status === Order::STATUS_SHIPPED) {
                    $this->orders->transition($order, Order::STATUS_DELIVERED, $actorId, 'Shipment delivered');
                } elseif ($order->status !== Order::STATUS_DELIVERED) {
                    throw ValidationException::withMessages([
                        'status' => ['Only a shipped order can be marked delivered.'],
                    ]);
                }
            }

            $shipment->fill([
                'tracking_number' => $data['tracking_number'] ?? $shipment->tracking_number,
                'carrier' => $data['carrier'] ?? $shipment->carrier,
                'status' => $to,
            ]);

            if (in_array($to, [Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED], true)) {
                $shipment->shipped_at ??= now();
            }
            if ($to === Shipment::STATUS_DELIVERED) {
                $shipment->delivered_at ??= now();
            }

            $shipment->save();
            if ($order->shopOrders()->exists()) {
                $this->fulfillment->synchronizeParent($order, $actorId);
            }

            return $shipment->load(['order.user', 'method']);
        });
    }

    /** @return array<string, array<int, string>> */
    private function transitions(): array
    {
        return [
            Shipment::STATUS_PENDING => [Shipment::STATUS_SHIPPED],
            Shipment::STATUS_SHIPPED => [Shipment::STATUS_IN_TRANSIT, Shipment::STATUS_DELIVERED, Shipment::STATUS_RETURNED],
            Shipment::STATUS_IN_TRANSIT => [Shipment::STATUS_DELIVERED, Shipment::STATUS_RETURNED],
            Shipment::STATUS_DELIVERED => [],
            Shipment::STATUS_RETURNED => [],
        ];
    }
}
