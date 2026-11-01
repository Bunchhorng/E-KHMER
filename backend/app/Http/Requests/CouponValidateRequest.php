<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CouponValidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            // Kept for old callers; pricing always comes from the server's cart.
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
