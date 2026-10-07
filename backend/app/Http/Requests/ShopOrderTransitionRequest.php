<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopOrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['confirmed', 'processing', 'shipped', 'delivered'])],
            'note' => ['nullable', 'string', 'max:255'],
            'carrier' => ['required_if:status,shipped', 'nullable', 'string', 'max:255'],
            'tracking_number' => ['required_if:status,shipped', 'nullable', 'string', 'max:255'],
        ];
    }
}
