<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Services\BakongService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
        protected CartService $carts,
        protected CouponService $coupons,
    ) {}

    protected function user(Request $request)
    {
        if ($request->user() !== null) {
            return $request->user();
        }

        return auth('sanctum')->user();
    }

    public function begin(CheckoutRequest $request)
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null) {
            return $this->createOrder($request);
        }
        if (! preg_match('/^[A-Za-z0-9_-]{8,80}$/', $key)) {
            throw ValidationException::withMessages(['idempotency' => 'Invalid checkout request identifier.']);
        }
        $owner = hash('sha256', $this->user($request) ? 'user:'.$this->user($request)->id : 'guest:'.$request->header('X-Session-Id'));
        $payload = $request->validated();
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $key, $owner, $hash) {
            $attempt = CheckoutAttempt::firstOrCreate(['owner_key' => $owner, 'request_key' => $key], ['payload_hash' => $hash]);
            $attempt = CheckoutAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($attempt->payload_hash, $hash)) {
                throw ValidationException::withMessages(['idempotency' => 'Checkout details changed. Start a new request.']);
            }
            if ($attempt->order_id) {
                $order = $this->resolveOwnedOrder($request, Order::findOrFail($attempt->order_id)->order_number);

                return (new OrderResource($order->load(['shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents'])))->response();
            }
            $response = $this->createOrder($request);
            if ($response->getStatusCode() === 201) {
                $attempt->update(['order_id' => Order::where('order_number', $response->getData()->data->order_number)->value('id')]);
            }

            return $response;
        });
    }

    protected function createOrder(CheckoutRequest $request)
    {
        $user = $this->user($request);
        $sessionId = $request->header('X-Session-Id');

        if ($user === null && ($sessionId === null || trim($sessionId) === '')) {
            return response()->json([
                'message' => 'A session identifier is required for guest checkout.',
            ], 422);
        }

        $cart = $this->carts->forUser($user, $sessionId);

        $address = $this->resolveAddress($request, $user, $sessionId);

        $subtotal = $this->subtotalCart($cart);

        $coupon = null;
        if ($request->filled('coupon_code')) {
            $coupon = $this->coupons->validate($user, (string) $request->coupon_code, $subtotal);
        }

        $order = $this->checkout->begin([
            'cart' => $cart,
            'session_id' => $user === null ? (string) $sessionId : null,
            'session_token' => $user === null ? 'guest_'.md5((string) $sessionId) : null,
            'shipping_method_id' => (int) $request->shipping_method_id,
            'shipping_methods' => $request->input('shipping_methods'),
            'payment_method' => (string) $request->payment_method,
            'coupon_code' => $request->coupon_code,
            'coupon' => $coupon,
            'address' => $address,
            'email' => $request->email ?? $user?->email,
            'customer_name' => $address['full_name'] ?? $user?->name,
            'phone' => $address['phone'] ?? null,
            'note' => $request->note,
        ], $user);

        return (new OrderResource($order))
            ->additional([
                'reservation_expires_at' => $order->placed_at?->addMinutes((int) config('ecommerce.reservation_minutes', 15))->toISOString(),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function confirm(Request $request, string $orderNumber)
    {
        $order = $this->resolveOwnedOrder($request, $orderNumber);

        $order = $this->checkout->confirm($order, $request->input('transaction_id'));

        return new OrderResource($order);
    }

    public function cancel(Request $request, string $orderNumber)
    {
        $order = $this->resolveOwnedOrder($request, $orderNumber);

        $this->checkout->release($order);

        return new OrderResource($order->fresh(['items.shop', 'payment', 'shipments', 'trackingEvents', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents']));
    }

    public function bakongPayments(Request $request, string $orderNumber, BakongService $bakong)
    {
        return response()->json(['data' => $bakong->prepare($this->resolveOwnedOrder($request, $orderNumber))->map(fn ($p) => [
            'id' => $p->id, 'shop_name' => $p->shop?->name, 'amount' => (float) $p->amount,
            'currency' => $p->currency, 'status' => $p->status, 'qr_payload' => $p->qr_payload,
            'expires_at' => $p->expires_at->toISOString(),
        ])]);
    }

    public function verifyBakong(Request $request, string $orderNumber, BakongService $bakong)
    {
        $order = $this->resolveOwnedOrder($request, $orderNumber);
        abort_unless($order->payment?->method === 'bakong', 422);
        $bakong->verify($order, $this->checkout);

        return new OrderResource($order->fresh(['items.shop', 'payment', 'shipments', 'trackingEvents', 'shopOrders.shop', 'shopOrders.items.shop', 'shopOrders.shipment', 'shopOrders.trackingEvents']));
    }

    protected function resolveOwnedOrder(Request $request, string $orderNumber): Order
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if ($order === null) {
            abort(404, 'Order not found.');
        }

        $currentUser = $this->user($request);

        if ($currentUser !== null) {
            if ((int) $order->user_id !== (int) $currentUser->id) {
                abort(404, 'Order not found.');
            }

            return $order->load(['items.shop', 'payment', 'shipments', 'trackingEvents']);
        }

        $sessionId = $request->header('X-Session-Id');
        $stored = $order->payment?->provider_data['session_id'] ?? null;

        if ($sessionId === null || $stored !== 'guest_'.md5((string) $sessionId)) {
            abort(404, 'Order not found.');
        }

        return $order->load(['items.shop', 'payment', 'shipments', 'trackingEvents']);
    }

    protected function resolveAddress(CheckoutRequest $request, $user, ?string $sessionId): array
    {
        if ($user !== null && $request->filled('address_id')) {
            $address = $user->addresses()->find($request->address_id);
            if ($address === null) {
                abort(404, 'Address not found.');
            }

            return [
                'full_name' => $address->full_name,
                'phone' => $address->phone,
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
            ];
        }

        $address = (array) $request->input('address', []);

        return [
            'full_name' => $address['full_name'] ?? null,
            'phone' => $address['phone'] ?? null,
            'address_line1' => $address['address_line1'] ?? null,
            'address_line2' => $address['address_line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? 'US',
        ];
    }

    protected function subtotalCart($cart): float
    {
        $subtotal = 0.0;
        foreach ($cart->items()->with('variant')->get() as $item) {
            $subtotal += ((float) ($item->variant?->price ?? 0)) * (int) $item->quantity;
        }

        return round($subtotal, 2);
    }
}
