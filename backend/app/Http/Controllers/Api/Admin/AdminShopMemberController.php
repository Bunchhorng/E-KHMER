<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminShopMemberController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([ShopUser::ROLE_OWNER, ShopUser::ROLE_MANAGER])],
            'q' => ['nullable', 'string', 'max:190'],
        ]);

        $members = ShopUser::query()
            ->with(['user:id,name,email,phone,avatar', 'shop:id,name,slug,logo,status'])
            ->where('role_in_shop', $data['role'])
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
}
