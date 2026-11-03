<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => $this->acceptsStatusFilter()
                ? ['nullable', Rule::in($this->allowedStatuses())]
                : ['nullable', 'prohibited'],
            // Report exports do not currently support caller-controlled
            // ordering. Reject it rather than accepting an ignored or unsafe
            // value that could later reach orderBy().
            'sort_by' => ['nullable', 'prohibited'],
            'sort_direction' => ['nullable', 'prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || (! $this->isPdf() && ! $this->isCsv()) || ! $this->filled(['from', 'to'])) {
                return;
            }

            $days = Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to')));

            if ($this->isPdf() && $days > 90) {
                $validator->errors()->add('to', 'PDF exports are limited to 91 days. Narrow the date range or use CSV.');
            }

            if ($this->isCsv() && $days > 365) {
                $validator->errors()->add('to', 'CSV exports are limited to 366 days. Narrow the date range before exporting.');
            }
        }];
    }

    public function from(): ?Carbon
    {
        return $this->filled('from') ? Carbon::createFromFormat('Y-m-d', $this->input('from'))->startOfDay() : null;
    }

    public function to(): ?Carbon
    {
        return $this->filled('to') ? Carbon::createFromFormat('Y-m-d', $this->input('to'))->endOfDay() : null;
    }

    private function isPdf(): bool
    {
        return $this->route()?->getActionMethod() === 'ordersPdf';
    }

    private function isCsv(): bool
    {
        return in_array($this->route()?->getActionMethod(), ['ordersCsv', 'productsCsv', 'paymentsCsv'], true);
    }

    private function allowedStatuses(): array
    {
        if ($this->route()?->getActionMethod() === 'paymentsCsv') {
            return [
                'all',
                Payment::STATUS_PENDING,
                Payment::STATUS_COMPLETED,
                Payment::STATUS_FAILED,
                Payment::STATUS_REFUNDED,
            ];
        }

        return [
            'all',
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
            Order::STATUS_REFUNDED,
        ];
    }

    private function acceptsStatusFilter(): bool
    {
        return in_array($this->route()?->getActionMethod(), ['ordersCsv', 'ordersPdf', 'paymentsCsv'], true);
    }
}
