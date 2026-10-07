<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminShopMemberController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([ShopUser::ROLE_OWNER, ShopUser::ROLE_MANAGER])],
            'q' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', Rule::in([ShopUser::STATUS_ACTIVE, ShopUser::STATUS_SUSPENDED])],
            'shop_status' => ['nullable', Rule::in(['pending', 'active', 'suspended', 'rejected', 'closed'])],
        ]);

        $members = ShopUser::query()
            ->with(['user:id,name,email,phone,avatar', 'shop:id,name,slug,logo,status'])
            ->where('role_in_shop', $data['role'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $data['status']))
            ->when($request->filled('shop_status'), fn ($query) => $query->whereHas('shop', fn ($shop) => $shop->where('status', $data['shop_status'])))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($membership) use ($term) {
                    $membership->whereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('shop', fn ($shop) => $shop->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate(20);

        $members->through(fn (ShopUser $member) => [
            'id' => $member->id,
            'status' => $member->status,
            'role' => $member->role_in_shop,
            'joined_at' => $member->created_at,
            'user' => [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'phone' => $member->user->phone,
                'avatar' => $member->user->avatar,
            ],
            'shop' => [
                'id' => $member->shop->id,
                'name' => $member->shop->name,
                'slug' => $member->shop->slug,
                'logo' => $member->shop->logo,
                'status' => $member->shop->status,
            ],
        ]);

        return response()->json([
            'data' => $members->items(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => $data['password'],
        ]);
        $member = ShopUser::create([
            'shop_id' => $data['shop_id'], 'user_id' => $user->id, 'role_in_shop' => $data['role'], 'status' => $data['status'],
        ]);

        return response()->json(['data' => $this->member($member)], 201);
    }

    public function show(ShopUser $shopMember)
    {
        return response()->json(['data' => $this->member($shopMember)]);
    }

    public function update(Request $request, ShopUser $shopMember)
    {
        $data = $request->validate($this->rules($shopMember));
        $shopMember->user->fill([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
        ]);
        if (!empty($data['password'])) $shopMember->user->password = $data['password'];
        $shopMember->user->save();
        $shopMember->update(['shop_id' => $data['shop_id'], 'status' => $data['status']]);

        return response()->json(['data' => $this->member($shopMember->refresh())]);
    }

    private function rules(?ShopUser $member = null): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($member?->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$member ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')],
            'role' => ['required', Rule::in([ShopUser::ROLE_OWNER, ShopUser::ROLE_MANAGER])],
            'status' => ['required', Rule::in([ShopUser::STATUS_ACTIVE, ShopUser::STATUS_SUSPENDED])],
        ];
    }

    private function member(ShopUser $member): array
    {
        $member->loadMissing(['user:id,name,email,phone,avatar', 'shop:id,name,slug,logo,status']);
        return [
            'id' => $member->id, 'status' => $member->status, 'role' => $member->role_in_shop, 'joined_at' => $member->created_at,
            'user' => ['id' => $member->user->id, 'name' => $member->user->name, 'email' => $member->user->email, 'phone' => $member->user->phone, 'avatar' => $member->user->avatar],
            'shop' => ['id' => $member->shop->id, 'name' => $member->shop->name, 'slug' => $member->shop->slug, 'logo' => $member->shop->logo, 'status' => $member->shop->status],
        ];
    }
}
