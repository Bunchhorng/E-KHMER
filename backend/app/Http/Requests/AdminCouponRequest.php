<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uniqueCode = Rule::unique('coupons', 'code');
        $coupon = $this->route('coupon');
        if ($coupon instanceof Coupon) {
            $uniqueCode->ignore($coupon);
        }

        return [
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'code' => ['bail', 'required', 'string', 'max:50', $uniqueCode],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => [
                'required',
                'numeric',
                'gt:0',
                Rule::when($this->input('type') === 'percentage', ['max:100']),
            ],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => Coupon::duplicateCodeMessage()];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Coupon::normalizeCode($this->input('code'))]);
        }
    }
}
