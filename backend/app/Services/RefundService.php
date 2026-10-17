<?php

namespace App\Services;

use App\Models\BakongPayment;
use App\Models\Order;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(private BakongService $bakong) {}

    public static function memo(Order $order, ?int $returnId = null): string
    {
        return 'REFUND-'.$order->order_number.($returnId === null ? '-CANCEL' : '-R'.$returnId);
    }

    public function record(Order $order, ?BakongPayment $payment, string $amount, string $reference, int $actor, ?int $returnId = null): PaymentRefund
    {
        $transfer = null;
        if ($payment !== null) {
            $transfer = $this->bakong->transaction($reference, true);
            if ($transfer === null || ! $payment->payer_account) {
                throw ValidationException::withMessages(['reference' => 'The refund transfer was not found.']);
            }
            $this->bakong->assertTransfer($transfer, $payment->payer_account, $amount, $payment->currency, $payment->receiver_account);
            if (strtolower($transfer['hash']) !== strtolower($reference) || ($transfer['description'] ?? null) !== self::memo($order, $returnId)) {
                throw ValidationException::withMessages(['reference' => 'The bank transfer hash and refund memo must match this specific refund.']);
            }
            $reference = strtolower($transfer['hash']);
        } elseif ($order->payment?->method !== 'cod') {
            throw ValidationException::withMessages(['reference' => 'This payment method requires a verified gateway refund.']);
        }

        return DB::transaction(function () use ($order, $payment, $amount, $reference, $actor, $returnId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($payment !== null) {
                $payment = BakongPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            }
            $previous = PaymentRefund::where('order_id', $order->id)->when($payment, fn ($q) => $q->where('bakong_payment_id', $payment->id))->sum('amount');
            $limit = $payment?->amount ?? $order->payment?->amount ?? 0;
            if ((float) $amount <= 0 || round((float) $previous + (float) $amount, 2) > round((float) $limit, 2)) {
                throw ValidationException::withMessages(['amount' => 'Refund exceeds the remaining payment balance.']);
            }
            if (PaymentRefund::where('reference', $reference)->exists() || BakongPayment::where('transaction_hash', $reference)->exists()) {
                throw ValidationException::withMessages(['reference' => 'This bank or receipt reference has already been used.']);
            }
            $refund = PaymentRefund::create(['order_id' => $order->id, 'bakong_payment_id' => $payment?->id,
                'order_return_id' => $returnId, 'amount' => $amount, 'currency' => $order->currency, 'reference' => $reference,
                'method' => $payment ? 'bakong_verified' : 'cash_receipt', 'recorded_by' => $actor]);
            if ($payment && round((float) $previous + (float) $amount, 2) === round((float) $payment->amount, 2)) {
                $payment->update(['status' => 'refunded', 'refunded_at' => now(), 'refund_hash' => $reference]);
            }
            $totalRefunded = (float) PaymentRefund::where('order_id', $order->id)->sum('amount');
            $fully = round($totalRefunded, 2) >= round((float) $order->total, 2);
            if ($order->status === 'cancelled' && $payment !== null && ! BakongPayment::where('order_id', $order->id)->whereIn('status', ['paid', 'refund_required'])->exists()) {
                $fully = true;
            }
            $order->update(['payment_status' => $fully ? 'refunded' : ($order->status === 'cancelled' ? 'refund_pending' : 'partially_refunded')]);
            if ($fully) {
                $order->payment?->update(['status' => 'refunded']);
            }

            return $refund;
        });
    }
}
