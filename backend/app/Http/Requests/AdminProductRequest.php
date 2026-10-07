<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStoring = $this->isMethod('post');

        return [
            'name' => [$isStoring ? 'required' : 'nullable', 'string', 'max:255'],
            // The slug is normalised to a web-safe alphabet in
            // prepareForValidation(), so the shape is guaranteed rather than rejected.
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            // products.short_description is a varchar(255); without a bound an
            // over-long value raised a truncation error and surfaced as a 500.
            'short_description' => ['nullable', 'string', 'max:255'],
            // products.description is a text column (65 535 bytes).
            'description' => ['nullable', 'string', 'max:60000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            // products.sku carries a unique index, so the clash has to be a 422
            // instead of an unhandled driver exception.
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($this->route('product')?->id),
            ],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['nullable', 'string', 'max:255'],
            'variants' => ['nullable', 'array', 'max:200'],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.quantity' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'variants.*.attributes' => ['nullable', 'array'],
            'variants.*.attributes.*.attribute' => ['required_with:variants.*.attributes', 'string', 'max:255'],
            'variants.*.attributes.*.value' => ['required_with:variants.*.attributes', 'string', 'max:255'],
        ];
    }

    public function prepareForValidation(): void
    {
        $hadSlug = $this->exists('slug');
        $slug = Str::slug(trim((string) $this->input('slug')));

        if ($slug === '') {
            $slug = Str::slug((string) $this->input('name'));
        }

        if ($slug !== '') {
            $this->merge(['slug' => $slug]);
        } elseif ($hadSlug) {
            // A non-latin name can slugify to nothing; hand the decision to the
            // controller instead of failing the whole payload on the regex.
            $this->merge(['slug' => null]);
        }

        if ($this->has('sku')) {
            $this->merge(['sku' => $this->trimmedOrNull('sku')]);
        }
    }

    protected function trimmedOrNull(string $key): ?string
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
