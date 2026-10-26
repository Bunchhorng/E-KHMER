<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BusinessForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'metric' => ['sometimes', Rule::in(['revenue', 'profit', 'expense', 'loss', 'orders', 'average_order_value'])],
            'period' => ['sometimes', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'horizon' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'currency' => ['sometimes', Rule::in(['USD', 'KHR'])],
            'shop_id' => ['nullable', 'integer', Rule::exists('shops', 'id')->whereNull('deleted_at')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $options = $this->options();
            if (Carbon::parse($options['from'])->greaterThan(Carbon::parse($options['to']))
                || Carbon::parse($options['from'])->diffInMonths(Carbon::parse($options['to'])) > 60) {
                $validator->errors()->add('from', 'Choose an ordered date range of 60 months or less.');
            }
        }];
    }

    public function options(): array
    {
        $to = Carbon::parse($this->input('to') ?: now()->toDateString());

        return [
            'metric' => $this->input('metric', 'profit'),
            'period' => $this->input('period', 'monthly'),
            'horizon' => (int) $this->input('horizon', 3),
            'from' => $this->input('from') ?: $to->copy()->subMonthsNoOverflow(35)->startOfMonth()->toDateString(),
            'to' => $to->toDateString(),
            'currency' => $this->input('currency', 'USD'),
        ];
    }
}
