<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopApplicationController extends Controller
{
    public function show(Request $request)
    {
        $shop = $request->user()->shops()->wherePivot('role_in_shop', 'owner')->latest('shops.id')->first();

        return $shop === null ? response()->noContent() : new ShopResource($shop);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->shops()->wherePivot('role_in_shop', 'owner')->exists()) {
            throw ValidationException::withMessages(['shop' => ['You already own a shop application.']]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:30'],
            'address_line' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'], 'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $shop = DB::transaction(function () use ($data, $user) {
            $base = Str::slug($data['name']) ?: 'shop'; $slug = $base; $suffix = 2;
            while (Shop::withTrashed()->where('slug', $slug)->exists()) $slug = $base.'-'.$suffix++;
            $shop = Shop::create(array_merge($data, ['slug' => $slug, 'code' => strtoupper(Str::substr(str_replace('-', '', $slug), 0, 30)), 'status' => Shop::STATUS_PENDING]));
            $shop->users()->attach($user->id, ['role_in_shop' => 'owner', 'status' => 'active']);
            return $shop;
        });

        return (new ShopResource($shop))->response()->setStatusCode(201);
    }
}
