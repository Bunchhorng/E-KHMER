<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'shipping_methods' => ['sometimes', 'array', 'max:50'],
            'shipping_methods.*' => ['required', 'integer', 'exists:shipping_methods,id'],
        ];
    }
}
