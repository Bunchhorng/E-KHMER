<?php

namespace App\Services;

use App\Http\Resources\ShopOrderResource;
use App\Http\Resources\ShopResource;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Shop;

class SellerDashboardService
{
    public function overview(Shop $shop): array
    {
        $orders = $shop->shopOrders()->whereHas('order');
        $actionable = $shop->shopOrders()->whereHas('order', fn ($query) => $query->whereNotIn('status', ['pending', 'cancelled', 'refunded']));
        $counts = (clone $actionable)->whereDoesntHave('shipment', fn ($query) => $query->where('status', 'returned'))
            ->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $stock = $shop->inventories()->whereHas('variant', fn ($query) => $query->where('is_active', true)
            ->whereHas('product', fn ($product) => $product->where('is_active', true)));
        $lowStock = (clone $stock)->whereRaw('quantity - reserved_quantity <= low_stock_threshold');

        return [
            'shop' => new ShopResource($shop),
            'metrics' => [
                'products' => $shop->products()->count(),
                'active_products' => $shop->products()->where('is_active', true)->count(),
                'inactive_products' => $shop->products()->where('is_active', false)->count(),
                'low_stock' => (clone $lowStock)->count(),
                'in_stock' => (clone $stock)->whereRaw('quantity - reserved_quantity > 0')->count(),
                'orders' => (clone $orders)->count(),
                'to_pack' => (int) ($counts[Order::STATUS_CONFIRMED] ?? 0),
                'to_ship' => (int) ($counts[Order::STATUS_PROCESSING] ?? 0),
                'on_way' => (int) ($counts[Order::STATUS_SHIPPED] ?? 0),
                'returns' => (clone $actionable)->whereHas('shipment', fn ($query) => $query->where('status', 'returned'))->count(),
            ],
            'recent_orders' => ShopOrderResource::collection((clone $orders)->with(['order', 'shop', 'items.shop', 'shipment', 'trackingEvents'])->latest('id')->limit(5)->get()),
            'low_stock' => $lowStock->with('variant.product')->orderByRaw('quantity - reserved_quantity')->limit(5)->get()->map(fn (Inventory $inventory) => [
                'id' => $inventory->id,
                'name' => $inventory->variant->product->name,
                'sku' => $inventory->variant->sku,
                'available' => $inventory->available_quantity,
                'threshold' => $inventory->low_stock_threshold,
            ])->values(),
        ];
    }
}
