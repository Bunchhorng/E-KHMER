<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminInventoryAdjustRequest;
use App\Http\Resources\InventoryResource;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Inventory::class);

        $query = Inventory::query()->with([
            'shop',
            'variant.product',
            'variant.attributeValues.value.attribute',
        ]);

        if ($request->filled('shop_id')) {
            $request->shop_id === 'none'
                ? $query->whereNull('shop_id')
                : $query->where('shop_id', (int) $request->shop_id);
        }

        if ($request->filled('q')) {
            $term = mb_strtolower(trim((string) $request->q));
            $query->where(function ($q) use ($term) {
                $q->whereHas('variant.product', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]))
                    ->orWhereHas('variant', fn ($v) => $v->whereRaw('LOWER(sku) LIKE ?', ["%{$term}%"]));
            });
        }

        if ($request->filled('stock_status') && $request->stock_status !== 'all') {
            match ($request->stock_status) {
                'in' => $query->whereRaw('quantity - reserved_quantity > low_stock_threshold'),
                'out' => $query->whereRaw('quantity - reserved_quantity <= 0'),
                'low' => $query->whereRaw('quantity - reserved_quantity <= low_stock_threshold'),
                default => null,
            };
        }

        $paginator = $query->orderByDesc('id')->paginate(15);

        return [
            'data' => InventoryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function transactions(Inventory $inventory, Request $request)
    {
        $this->authorize('view', $inventory);

        $query = $inventory->transactions()->with('createdBy');

        if ($request->filled('type') && in_array($request->type, ['reserve', 'release', 'deduct', 'adjust'], true)) {
            $query->where('type', $request->type);
        }

        $paginator = $query->orderByDesc('created_at')->paginate(20);

        return [
            'data' => InventoryTransactionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function adjust(AdminInventoryAdjustRequest $request, Inventory $inventory, InventoryService $inventoryService)
    {
        $this->authorize('update', $inventory);

        $inventoryService->adjust(
            (int) $inventory->product_variant_id,
            (int) $request->validated('quantity'),
            (int) $request->user()->id,
        );

        $inventory->refresh()->load([
            'shop',
            'variant.product',
            'variant.attributeValues.value.attribute',
        ]);

        return new InventoryResource($inventory);
    }
}
