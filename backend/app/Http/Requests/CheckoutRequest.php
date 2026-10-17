<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => ['required_without:shipping_methods', 'nullable', 'integer', 'exists:shipping_methods,id'],
            'shipping_methods' => ['sometimes', 'array', 'min:1', 'max:50'],
            'shipping_methods.*' => ['required', 'integer', 'exists:shipping_methods,id'],
            'coupon_code' => ['nullable', 'string'],
            'payment_method' => ['required', 'string', 'in:card,cod,bank,gateway,bakong'],
            'email' => ['nullable', 'email'],
            'note' => ['nullable', 'string', 'max:1000'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'address' => ['nullable', 'array'],
            'address.full_name' => ['required_without:address_id', 'string', 'max:255'],
            'address.phone' => ['nullable', 'string', 'max:30'],
            'address.address_line1' => ['required_without:address_id', 'string'],
            'address.address_line2' => ['nullable', 'string'],
            'address.city' => ['required_without:address_id', 'string'],
            'address.state' => ['required_without:address_id', 'string'],
            'address.postal_code' => ['required_without:address_id', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('payment_method')) {
            $this->merge(['payment_method' => 'card']);
        }
    }
}
