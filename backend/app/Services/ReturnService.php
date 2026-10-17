<?php

namespace App\Services;

use App\Models\BakongPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(private InventoryService $inventory, private RefundService $refunds) {}

    public function create(User $user, array $data, array $photos = []): OrderReturn
    {
        return DB::transaction(function () use ($user, $data, $photos) {
            $item = OrderItem::findOrFail($data['order_item_id']);
            $order = Order::whereKey($item->order_id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $allocation = $item->shopOrder()->with('shipment')->first();
            $deliveredAt = $allocation ? $allocation->shipment?->delivered_at : $order->shipments()->where('status', 'delivered')->max('delivered_at');
            if (($allocation?->status ?? $order->status) !== 'delivered' || ! $deliveredAt || now()->greaterThan(Carbon::parse($deliveredAt)->addDays(config('ecommerce.return_days', 14)))) {
                throw ValidationException::withMessages(['order_item_id' => 'This item is not eligible for a return within the configured return window.']);
            }
            if (! in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
                throw ValidationException::withMessages(['order_item_id' => 'The payment must be settled before requesting a refund.']);
            }
            $existing = OrderReturn::where('order_item_id', $item->id)->where('status', '!=', 'rejected')->get();
            $used = (int) $existing->sum('quantity');
            if ($used + $data['quantity'] > $item->quantity) {
                throw ValidationException::withMessages(['quantity' => 'Return quantity exceeds the items available to return.']);
            }
            // Partition each seller's net goods in cents, then apportion the
            // item's budget cumulatively. Three returns of a $1 item must add
            // up to $1, rather than three rounded $0.33 refunds.
            $basis = $allocation ?? $order;
            $netCents = (int) round(max(0, (float) $basis->subtotal - (float) $basis->discount_amount + (float) $basis->tax_amount) * 100);
            $items = $allocation ? $allocation->items()->orderBy('id')->get() : $order->items()->orderBy('id')->get();
            $weight = (float) $items->sum('line_total');
            $before = (float) $items->where('id', '<', $item->id)->sum('line_total');
            $budget = $weight > 0 ? (int) round($netCents * ($before + (float) $item->line_total) / $weight) - (int) round($netCents * $before / $weight) : 0;
            $committedCents = (int) round((float) $existing->sum('refund_amount') * 100);
            $amount = max(0, (int) round($budget * ($used + $data['quantity']) / $item->quantity) - $committedCents) / 100;

            return OrderReturn::create(['order_id' => $order->id, 'order_item_id' => $item->id, 'shop_id' => $item->shop_id,
                'user_id' => $user->id, 'quantity' => $data['quantity'], 'reason' => $data['reason'], 'photos' => $photos, 'refund_amount' => $amount]);
        });
    }

    public function transition(OrderReturn $return, string $status, User $actor, ?string $note, ?string $reference, bool $restock = false): OrderReturn
    {
        return DB::transaction(function () use ($return, $status, $actor, $note, $reference, $restock) {
            $order = Order::whereKey($return->order_id)->lockForUpdate()->firstOrFail();
            $return = OrderReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $map = ['requested' => ['approved', 'rejected'], 'approved' => ['received'], 'received' => ['refunded']];
            if (! in_array($status, $map[$return->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Invalid return status transition.']);
            }
            if ($status === 'rejected' && ! trim($note ?? '')) {
                throw ValidationException::withMessages(['note' => 'Explain why this request was rejected.']);
            }
            if ($status === 'received' && $restock && ! $return->restocked) {
                $this->inventory->restock($return->item->product_variant_id, $return->quantity);
                $return->restocked = true;
            }
            if ($status === 'refunded') {
                if (! $reference) {
                    throw ValidationException::withMessages(['reference' => 'Provide a Bakong refund transaction hash or a cash receipt reference.']);
                }
                $payment = BakongPayment::where('order_id', $order->id)->where('shop_id', $return->shop_id)->first();
                if ($order->payment?->method === 'bakong' && ! $payment) {
                    throw ValidationException::withMessages(['reference' => 'Original payment is missing.']);
                }
                $this->refunds->record($order, $payment, (string) $return->refund_amount, $reference, $actor->id, $return->id);
            }
            $return->fill(['status' => $status, 'resolution_note' => $note, 'reviewed_by' => $actor->id])->save();

            return $return->load(['order', 'item', 'shop']);
        });
    }
}
