<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SellerShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shop = $this->route('shop');
        $bankChanged = collect(['bakong_account_id', 'bakong_merchant_name', 'bakong_merchant_city'])
            ->contains(fn ($field) => $this->has($field) && $this->input($field) !== $shop->{$field});

        return ! $bankChanged || $this->user()->isSuperAdmin() || $shop->users()->whereKey($this->user()->id)->wherePivot('role_in_shop', 'owner')->wherePivot('status', 'active')->exists();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'bakong_account_id' => ['nullable', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_.-]+@[a-zA-Z0-9_.-]+$/'],
            'bakong_merchant_name' => ['nullable', 'string', 'max:25', 'regex:/^[ -~]+$/'],
            'bakong_merchant_city' => ['nullable', 'string', 'max:15', 'regex:/^[ -~]+$/'],
            'logo_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'banner_file' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_banner' => ['sometimes', 'boolean'],
        ];
    }
}
