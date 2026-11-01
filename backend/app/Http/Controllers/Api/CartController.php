<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartAddRequest;
use App\Http\Requests\CartUpdateRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(protected CartService $carts) {}

    protected function user(Request $request)
    {
        if ($request->user() !== null) {
            return $request->user();
        }

        return auth('sanctum')->user();
    }

    protected function resolveCart(Request $request): Cart
    {
        $cart = $this->carts->forUser($this->user($request), $this->sessionId($request));

        return $this->loadItems($cart);
    }

    private function loadItems(Cart $cart): Cart
    {
        return $cart->load(['items' => fn ($query) => $query->with(CartService::ITEM_RELATIONS)]);
    }

    protected function sessionId(Request $request): ?string
    {
        $sid = $request->header('X-Session-Id');

        if ($this->user($request) === null && ($sid === null || trim($sid) === '')) {
            throw ValidationException::withMessages([
                'session' => ['A session identifier is required for guest carts.'],
            ]);
        }

        return $sid;
    }

    public function index(Request $request)
    {
        return new CartResource($this->resolveCart($request));
    }

    public function add(CartAddRequest $request)
    {
        $cart = $this->carts->forUser($this->user($request), $this->sessionId($request));
        $this->carts->add($cart, (int) $request->product_variant_id, (int) $request->quantity);

        return (new CartResource($this->loadItems($cart)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(CartUpdateRequest $request, int $cartItem)
    {
        $cart = $this->carts->forUser($this->user($request), $this->sessionId($request));
        $this->carts->update($cart, (int) $cartItem, (int) $request->quantity);

        return new CartResource($this->loadItems($cart));
    }

    public function remove(Request $request, int $cartItem)
    {
        $cart = $this->carts->forUser($this->user($request), $this->sessionId($request));
        $this->carts->remove($cart, (int) $cartItem);

        return new CartResource($this->loadItems($cart));
    }

    public function clear(Request $request)
    {
        $cart = $this->carts->forUser($this->user($request), $this->sessionId($request));
        $this->carts->clear($cart);

        return new CartResource($this->loadItems($cart));
    }

    public function totals(Request $request)
    {
        $cart = $this->carts->forUser($this->user($request), $this->sessionId($request));

        return ['data' => $this->carts->totals($cart)];
    }
}
