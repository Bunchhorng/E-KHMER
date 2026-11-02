<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guest = ($this->user() ?? auth('sanctum')->user()) === null;

        return [
            'shipping_method_id' => ['required_without:shipping_methods', 'nullable', 'integer', 'exists:shipping_methods,id'],
            'shipping_methods' => ['sometimes', 'array', 'min:1', 'max:50'],
            'shipping_methods.*' => ['required', 'integer', 'exists:shipping_methods,id'],
            'coupon_code' => ['nullable', 'string'],
            'quote_fingerprint' => ['sometimes', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'payment_method' => ['required', 'string', 'in:card,cod,bank,gateway,bakong'],
            'email' => [Rule::requiredIf($guest), 'nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'address_id' => ['bail', Rule::prohibitedIf($guest), 'nullable', 'integer', 'exists:addresses,id'],
            'address' => [Rule::requiredIf($guest), 'nullable', 'array'],
            'address.full_name' => ['required_without:address_id', 'string', 'max:255'],
            'address.phone' => [Rule::requiredIf($guest), 'nullable', 'string', 'max:30'],
            'address.address_line1' => ['required_without:address_id', 'string', 'max:255'],
            'address.address_line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required_without:address_id', 'string', 'max:100'],
            'address.state' => ['required_without:address_id', 'string', 'max:100'],
            'address.postal_code' => ['required_without:address_id', 'string', 'max:20'],
            'address.country' => [Rule::requiredIf($guest), 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'address_id.prohibited' => 'Guest checkout requires a shipping address entered directly. Saved addresses require sign in.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'address.required' => 'Please enter your shipping address.',
            'address.phone.required' => 'Please enter a phone number for delivery.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('payment_method')) {
            $this->merge(['payment_method' => 'card']);
        }
    }
}
