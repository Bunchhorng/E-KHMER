<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AdminProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStoring = $this->isMethod('post');

        $rules = [
            'name' => [$isStoring ? 'required' : 'nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'string'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['nullable', 'string'],
            'variants.*.name' => ['nullable', 'string'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.attributes.*.attribute' => ['required_with:variants.*.attributes', 'string'],
            'variants.*.attributes.*.value' => ['required_with:variants.*.attributes', 'string'],
        ];

        return $rules;
    }

    public function prepareForValidation(): void
    {
        if (!$this->filled('slug') && $this->filled('name')) {
            $this->merge([
                'slug' => Str::slug($this->input('name')),
            ]);
        }
    }
}
