<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'range' => ['sometimes', 'required', Rule::in(['today', 'yesterday', '7', '30', 'this_month', 'last_month', 'custom'])],
            'from' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('range') !== 'custom') {
                return;
            }

            if (Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to'))) > 365) {
                $validator->errors()->add('to', 'Choose a date range of 366 days or less.');
            }
        }];
    }
}
