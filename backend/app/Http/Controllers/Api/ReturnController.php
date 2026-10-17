<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderReturnResource;
use App\Models\OrderReturn;
use App\Models\Shop;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReturnController extends Controller
{
    private function canManage(Request $request, OrderReturn $return): bool
    {
        $shop = $request->route('shop');

        return $request->user()->isSuperAdmin() || ($shop instanceof Shop && (int) $return->shop_id === (int) $shop->id && $request->user()->ownsShop($shop));
    }

    public function index(Request $request)
    {
        $query = OrderReturn::with(['order', 'item', 'shop']);
        $shop = $request->route('shop');
        if ($shop instanceof Shop) {
            $query->where('shop_id', $shop->id);
        } elseif (! $request->user()->isSuperAdmin() || ! $request->is('api/admin/*')) {
            $query->where('user_id', $request->user()->id);
        }

        return OrderReturnResource::collection($query->latest()->paginate(15));
    }

    public function store(Request $request, ReturnService $returns)
    {
        $data = $request->validate(['order_item_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'], 'photos' => ['sometimes', 'array', 'max:3'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048']]);
        $paths = [];
        try {
            foreach ($request->file('photos', []) as $photo) {
                $path = $photo->store('returns', 'local');
                if ($path === false) {
                    throw new \RuntimeException('Could not store return evidence.');
                }
                $paths[] = $path;
            }
            $return = $returns->create($request->user(), $data, $paths);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return (new OrderReturnResource($return->load(['order', 'item', 'shop'])))->response()->setStatusCode(201);
    }

    public function transition(Request $request, OrderReturn $orderReturn, ReturnService $returns): OrderReturnResource
    {
        abort_unless($this->canManage($request, $orderReturn), 404);
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,received,refunded'],
            'note' => ['nullable', 'string', 'max:2000'], 'reference' => ['nullable', 'string', 'max:190'], 'restock' => ['sometimes', 'boolean']]);

        return new OrderReturnResource($returns->transition($orderReturn, $data['status'], $request->user(), $data['note'] ?? null, $data['reference'] ?? null, $request->boolean('restock')));
    }

    public function photo(Request $request, OrderReturn $orderReturn, int $index)
    {
        abort_unless((int) $orderReturn->user_id === (int) $request->user()->id || $this->canManage($request, $orderReturn), 404);
        $path = $orderReturn->photos[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
