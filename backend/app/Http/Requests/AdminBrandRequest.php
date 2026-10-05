<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('brands', 'slug')->ignore($this->route('brand')?->id),
            ],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug') || $this->filled('name')) {
            $slug = Str::slug((string) ($this->input('slug') ?: $this->input('name')));

            if ($slug !== '') {
                $this->merge(['slug' => $slug]);
            }
        }
    }
}
