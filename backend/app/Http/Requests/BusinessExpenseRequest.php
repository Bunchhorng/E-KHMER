<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'incurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category' => ['required', Rule::in(['rent', 'payroll', 'shipping', 'marketing', 'fees', 'other'])],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'shop_id' => ['nullable', 'integer', Rule::exists('shops', 'id')->whereNull('deleted_at')],
        ];
    }
}
