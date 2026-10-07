<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShopOrder;
use App\Notifications\OrderStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopOrderFulfillmentService
{
    private const STAGES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

    public function transition(ShopOrder $allocation, string $status, ?int $actorId, ?string $note = null, array $shipping = []): ShopOrder
    {
        return DB::transaction(function () use ($allocation, $status, $actorId, $note, $shipping): ShopOrder {
            $order = Order::whereKey($allocation->order_id)->lockForUpdate()->firstOrFail();
            $allocation = ShopOrder::with(['shipment', 'shop'])->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $allocation->setRelation('order', $order);
            $this->assertOpen($order);

            if ($allocation->status === $status) {
                return $this->load($allocation);
            }
            if (! in_array($status, $allocation->allowedTransitions(), true)) {
                throw ValidationException::withMessages(['status' => ["Invalid shop order transition from {$allocation->status} to {$status}."]]);
            }

            $shipment = $this->ensureShipment($allocation, $order);
            if ($status === Order::STATUS_SHIPPED) {
                $shipment->fill($shipping);
                if (! trim((string) $shipment->carrier) || ! trim((string) $shipment->tracking_number)) {
                    throw ValidationException::withMessages(['tracking_number' => ['A carrier and tracking number are required before dispatch.']]);
                }
                $shipment->status = Shipment::STATUS_SHIPPED;
                $shipment->shipped_at = now();
            }
            if ($status === Order::STATUS_DELIVERED) {
                $shipment->status = Shipment::STATUS_DELIVERED;
                $shipment->delivered_at = now();
            }
            $shipment->save();
            $this->move($allocation, $status, $actorId, $note);
            $this->synchronizeParent($order, $actorId);
            $this->notifyShop($allocation, $order, $status);

            return $this->load($allocation);
        });
    }

    public function updateShipment(ShopOrder $allocation, array $data, ?int $actorId): ShopOrder
    {
        return DB::transaction(function () use ($allocation, $data, $actorId): ShopOrder {
            $order = Order::whereKey($allocation->order_id)->lockForUpdate()->firstOrFail();
            $allocation = ShopOrder::with(['shipment', 'shop'])->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $allocation->setRelation('order', $order);
            $this->assertOpen($order);
            $shipment = $this->ensureShipment($allocation, $order);
            $from = $shipment->status;
            $to = $data['status'];
            $transitions = [
                'pending' => ['shipped'],
                'shipped' => ['in_transit', 'delivered', 'returned'],
                'in_transit' => ['delivered', 'returned'],
                'delivered' => [],
                'returned' => [],
            ];
            if ($from !== $to && ! in_array($to, $transitions[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => ["Invalid shipment transition from {$from} to {$to}."]]);
            }
            $metadata = array_intersect_key($data, array_flip(['tracking_number', 'carrier']));
            if ($from !== $to && $to === Shipment::STATUS_SHIPPED) {
                return $this->transition($allocation, Order::STATUS_SHIPPED, $actorId, 'Shipment dispatched', $metadata);
            }
            if ($from !== $to && $to === Shipment::STATUS_DELIVERED) {
                return $this->transition($allocation, Order::STATUS_DELIVERED, $actorId, 'Shipment delivered', $metadata);
            }
            if (in_array($to, ['in_transit', 'returned'], true) && $allocation->status !== Order::STATUS_SHIPPED) {
                throw ValidationException::withMessages(['status' => ['Only a dispatched shop order can move through carrier transit or return.']]);
            }
            $shipment->fill($metadata);
            $shipment->status = $to;
            if ($to !== Shipment::STATUS_PENDING && (! trim((string) $shipment->carrier) || ! trim((string) $shipment->tracking_number))) {
                throw ValidationException::withMessages(['tracking_number' => ['A dispatched shipment must retain its carrier and tracking number.']]);
            }
            $shipment->save();
            if ($from !== $to || $shipment->wasChanged(['carrier', 'tracking_number'])) {
                $this->record($allocation, $from, $to, $actorId, $from === $to ? 'Shipment tracking updated' : 'Shipment '.$to);
            }

            return $this->load($allocation);
        });
    }

    /** The parent is already locked by checkout or the platform order service. */
    public function synchronizeAll(Order $order, string $status, ?int $actorId = null, ?string $note = null): void
    {
        foreach ($order->shopOrders()->with('shipment')->lockForUpdate()->get() as $allocation) {
            if ($allocation->status === $status) {
                continue;
            }
            $currentRank = array_search($allocation->status, self::STAGES, true);
            $nextRank = array_search($status, self::STAGES, true);
            if ($nextRank !== false && ($currentRank === false || $currentRank > $nextRank)) {
                continue;
            }
            if (in_array($status, [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED], true) && $allocation->shipment?->status === Shipment::STATUS_RETURNED) {
                throw ValidationException::withMessages(['status' => ['A returned delivery must be resolved before the whole order can advance.']]);
            }
            $this->move($allocation, $status, $actorId, $note ?? 'Platform order '.$status);
            if (in_array($status, [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED], true)) {
                $shipment = $this->ensureShipment($allocation, $order);
                if ($shipment->status !== Shipment::STATUS_RETURNED) {
                    $shipment->status = $status === Order::STATUS_DELIVERED ? Shipment::STATUS_DELIVERED : Shipment::STATUS_SHIPPED;
                    $shipment->shipped_at ??= now();
                    if ($status === Order::STATUS_DELIVERED) {
                        $shipment->delivered_at ??= now();
                    }
                    $shipment->save();
                }
            }
        }
    }

    /** Overall progress follows the least advanced allocation, preserving independent shop progress. */
    public function synchronizeParent(Order $order, ?int $actorId = null): void
    {
        if (in_array($order->status, ['pending', 'cancelled', 'refunded'], true)) {
            return;
        }
        $statuses = $order->shopOrders()->pluck('status');
        if ($statuses->isEmpty()) {
            return;
        }
        $ranks = $statuses->map(fn ($status) => array_search($status, self::STAGES, true));
        if ($ranks->containsStrict(false)) {
            return;
        }
        if ($order->items()->whereNull('shop_order_id')->exists()) {
            $legacyStatus = $order->shipments()->whereNull('shop_order_id')->first()?->status;
            $ranks->push(match ($legacyStatus) {
                Shipment::STATUS_DELIVERED => 4,
                Shipment::STATUS_SHIPPED, Shipment::STATUS_IN_TRANSIT => 3,
                default => 2,
            });
        }
        $status = self::STAGES[$ranks->min()];
        if (array_search($status, self::STAGES, true) <= array_search($order->status, self::STAGES, true)) {
            return;
        }
        $from = $order->status;
        $order->update(['status' => $status]);
        $order->trackingEvents()->create(['from_status' => $from, 'status' => $status, 'description' => 'All shop allocations reached '.$status, 'changed_by' => $actorId]);
    }

    public function load(ShopOrder $allocation): ShopOrder
    {
        return $allocation->load(['order.payment', 'shop', 'items.shop', 'shipment', 'trackingEvents.changedBy']);
    }

    private function assertOpen(Order $order): void
    {
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
            throw ValidationException::withMessages(['status' => ['A cancelled or refunded order cannot be fulfilled.']]);
        }
    }

    private function ensureShipment(ShopOrder $allocation, Order $order): Shipment
    {
        return $allocation->shipment()->firstOrCreate([], [
            'order_id' => $order->id,
            'shipping_method_id' => $order->shipments()->whereNull('shop_order_id')->first()?->shipping_method_id,
            'status' => match ($allocation->status) {
                Order::STATUS_SHIPPED => Shipment::STATUS_SHIPPED,
                Order::STATUS_DELIVERED => Shipment::STATUS_DELIVERED,
                default => Shipment::STATUS_PENDING,
            },
            'address_snapshot' => $order->shipping_address,
        ]);
    }

    private function move(ShopOrder $allocation, string $status, ?int $actorId, ?string $note): void
    {
        $from = $allocation->status;
        $allocation->update(['status' => $status]);
        $this->record($allocation, $from, $status, $actorId, $note ?? 'Shop order '.$status);
    }

    private function record(ShopOrder $allocation, string $from, string $status, ?int $actorId, string $description): void
    {
        $allocation->trackingEvents()->create(['order_id' => $allocation->order_id, 'from_status' => $from, 'status' => $status, 'description' => $description, 'changed_by' => $actorId]);
    }

    private function notifyShop(ShopOrder $allocation, Order $order, string $status): void
    {
        if ($order->user_id !== null) {
            DB::afterCommit(function () use ($allocation, $order, $status): void {
                $order->user?->notify(new OrderStatusNotification($order, $status, $allocation));
            });
        }
    }
}
