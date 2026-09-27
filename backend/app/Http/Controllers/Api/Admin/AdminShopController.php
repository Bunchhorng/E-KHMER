<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminShopController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Shop::class);

        $shops = Shop::query()
            ->withCount(['products', 'inventories'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return ShopResource::collection($shops);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Shop::class);

        $data = $request->validate($this->rules());

        $shop = Shop::create($data);

        return (new ShopResource($shop))->response()->setStatusCode(201);
    }

    public function show(Shop $shop)
    {
        $this->authorize('view', $shop);

        $shop->loadCount(['products', 'inventories', 'users']);

        return new ShopResource($shop);
    }

    public function update(Request $request, Shop $shop)
    {
        $this->authorize('update', $shop);

        $data = $request->validate($this->rules($shop));

        $shop->update($data);

        return new ShopResource($shop->refresh());
    }

    public function destroy(Shop $shop)
    {
        $this->authorize('delete', $shop);

        if ($shop->is_default) {
            return response()->json(['message' => 'The default shop cannot be deleted.'], 422);
        }

        $shop->delete();

        return response()->json(['message' => 'Shop deleted.']);
    }

    public function updateStatus(Request $request, Shop $shop)
    {
        $this->authorize('update', $shop);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                Shop::STATUS_PENDING,
                Shop::STATUS_ACTIVE,
                Shop::STATUS_SUSPENDED,
                Shop::STATUS_REJECTED,
                Shop::STATUS_CLOSED,
            ])],
        ]);

        if ($shop->is_default && $data['status'] !== Shop::STATUS_ACTIVE) {
            return response()->json(['message' => 'The default shop must stay active.'], 422);
        }

        $shop->update(['status' => $data['status']]);

        return new ShopResource($shop->refresh());
    }

    private function rules(?Shop $shop = null): array
    {
        return [
            'name' => [$shop ? 'sometimes' : 'required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('shops', 'slug', $shop?->id)],
            'code' => [$shop ? 'sometimes' : 'nullable', 'string', 'max:30', Rule::unique('shops', 'code', $shop?->id)],
            'branch_type' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:255'],
            'banner' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'mall' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['nullable', Rule::in([
                Shop::STATUS_PENDING,
                Shop::STATUS_ACTIVE,
                Shop::STATUS_SUSPENDED,
                Shop::STATUS_REJECTED,
                Shop::STATUS_CLOSED,
            ])],
            'is_default' => ['nullable', 'boolean'],
            'commission_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
