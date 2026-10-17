<?php

namespace App\Services;

use App\Models\BakongPayment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class BakongService
{
    public function __construct(private KhqrService $khqr) {}

    public function transaction(string $identifier, bool $fullHash = false): ?array
    {
        $token = config('ecommerce.bakong_token');
        if (! $token) {
            throw ValidationException::withMessages(['payment' => 'Bakong verification is not configured. Contact support.']);
        }
        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(15)
            ->post(rtrim(config('ecommerce.bakong_url'), '/').'/v1/check_transaction_by_'.($fullHash ? 'hash' : 'md5'), [$fullHash ? 'hash' : 'md5' => $identifier]);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['payment' => 'Bakong is unavailable. Your payment status has not been changed; please retry.']);
        }
        $data = $response->json();
        if (($data['responseCode'] ?? null) === 0 && is_array($data['data'] ?? null)) {
            return $data['data'];
        }
        if ((int) ($data['errorCode'] ?? 0) === 1) {
            return null;
        }
        throw ValidationException::withMessages(['payment' => 'Bakong could not verify this transaction. Please retry or contact support.']);
    }

    public function prepare(Order $order)
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->payment?->method === 'bakong', 422, 'This order does not use Bakong.');
            if ($order->status !== 'pending') {
                return BakongPayment::with('shop')->where('order_id', $order->id)->get();
            }
            $expires = $order->placed_at->copy()->addMinutes(config('ecommerce.reservation_minutes', 15));
            if ($expires->isPast()) {
                throw ValidationException::withMessages(['payment' => 'This payment window has expired. Start a new checkout.']);
            }
            foreach ($order->shopOrders()->with('shop')->get() as $allocation) {
                if (BakongPayment::where('shop_order_id', $allocation->id)->exists()) {
                    continue;
                }
                $shop = $allocation->shop;
                if (! $shop?->bakong_account_id || ! $shop->bakong_merchant_name || ! $shop->bakong_merchant_city) {
                    throw ValidationException::withMessages(['payment' => 'This shop has not completed its Bakong settings.']);
                }
                $qr = $this->khqr->generate($shop->bakong_account_id, $shop->bakong_merchant_name, $shop->bakong_merchant_city, number_format((float) $allocation->total, 2, '.', ''), $order->currency, $allocation->shop_order_number, $expires);
                BakongPayment::create(['order_id' => $order->id, 'shop_order_id' => $allocation->id, 'shop_id' => $shop->id,
                    'receiver_account' => $shop->bakong_account_id, 'amount' => $allocation->total, 'currency' => $order->currency,
                    'qr_payload' => $qr, 'md5' => md5($qr), 'expires_at' => $expires]);
            }

            return BakongPayment::with('shop')->where('order_id', $order->id)->get();
        });
    }

    public function verify(Order $order, CheckoutService $checkout): void
    {
        $payments = BakongPayment::where('order_id', $order->id)->get();
        foreach ($payments as $payment) {
            if ($payment->status !== 'pending') {
                continue;
            }
            $transaction = $this->transaction($payment->md5);
            if ($transaction === null) {
                $payment->touch();

                continue;
            }
            $this->assertTransfer($transaction, $payment->receiver_account, (string) $payment->amount, $payment->currency);
            DB::transaction(function () use ($order, $payment, $transaction) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $locked = BakongPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'pending') {
                    return;
                }
                if (BakongPayment::where('transaction_hash', $transaction['hash'])->whereKeyNot($locked->id)->exists()) {
                    throw ValidationException::withMessages(['payment' => 'This bank transaction has already been used.']);
                }
                $locked->update(['status' => $lockedOrder->status === 'pending' && $locked->expires_at->isFuture() ? 'paid' : 'refund_required',
                    'transaction_hash' => $transaction['hash'], 'payer_account' => $transaction['fromAccountId'] ?? null, 'paid_at' => now()]);
                if ($lockedOrder->status === 'pending') {
                    $lockedOrder->update(['payment_status' => 'partial']);
                } elseif ($locked->status === 'refund_required') {
                    $lockedOrder->update(['payment_status' => 'refund_pending']);
                    $lockedOrder->payment?->update(['status' => 'refund_pending']);
                }
            });
        }
        DB::transaction(function () use ($order, $checkout) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payments = BakongPayment::where('order_id', $order->id)->lockForUpdate()->get();
            if ($order->status === 'pending' && $payments->isNotEmpty() && $payments->every(fn ($p) => $p->status === 'paid')) {
                $checkout->confirm($order, 'BAKONG-'.$order->order_number, true);
            }
        });
    }

    public function assertTransfer(array $data, string $receiver, string $amount, string $currency, ?string $sender = null): void
    {
        if (! is_string($data['hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/i', $data['hash'])
            || ! is_string($data['fromAccountId'] ?? null) || $data['fromAccountId'] === ''
            || ($data['toAccountId'] ?? null) !== $receiver || ($data['currency'] ?? null) !== $currency
            || ! is_numeric($data['amount'] ?? null) || (int) round((float) $data['amount'] * 100) !== (int) round((float) $amount * 100)
            || ($sender !== null && ($data['fromAccountId'] ?? null) !== $sender)) {
            throw ValidationException::withMessages(['payment' => 'Bank transaction does not match the expected recipient, amount, or currency.']);
        }
    }
}
