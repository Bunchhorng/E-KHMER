<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SellerProductRequest extends AdminProductRequest
{
    public function prepareForValidation(): void
    {
        // Multipart requests encode the ordered gallery as JSON so an empty
        // array can explicitly remove all photos, too.
        if (is_string($this->input('images'))) {
            $images = json_decode($this->input('images'), true);
            if (is_array($images) && array_is_list($images)) {
                $this->merge(['images' => $images]);
            }
        }

        if (is_string($this->input('variants'))) {
            $variants = json_decode($this->input('variants'), true);
            if (is_array($variants) && array_is_list($variants)) {
                $this->merge(['variants' => $variants]);
            }
        }

        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'variants' => ['sometimes', 'array', 'min:1', 'max:200'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('product_variants', 'id')->where('product_id', $this->route('product')?->id ?? 0)->whereNull('deleted_at')],
            'variants.*.image_id' => ['nullable', 'integer', Rule::exists('product_images', 'id')->where('product_id', $this->route('product')?->id ?? 0)],
            'variants.*.is_default' => ['sometimes', 'boolean'],
            'images.*' => ['required', 'string', 'max:255', 'distinct'],
            'image_files' => ['sometimes', 'array', 'max:20'],
            'image_files.*' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $files = $this->file('image_files', []);
            if (array_sum(array_map(fn ($file) => $file->getSize(), $files)) > 10 * 1024 * 1024) {
                $validator->errors()->add('image_files', 'Upload at most 10 MB of new photos per save.');
            }

            $product = $this->route('product');
            $owned = $product instanceof Product ? $product->images()->pluck('image_path')->all() : [];
            $referenced = [];
            foreach ($this->input('images', []) ?? [] as $index => $path) {
                if (preg_match('/^upload:(0|[1-9][0-9]*)$/', $path, $match) && isset($files[(int) $match[1]])) {
                    $referenced[] = (int) $match[1];
                } elseif (! in_array($path, $owned, true)) {
                    $validator->errors()->add("images.{$index}", 'Choose a new photo or an existing photo from this product.');
                }
            }

            if (count($referenced) !== count($files)) {
                $validator->errors()->add('images', 'Every uploaded photo must be included in the product gallery.');
            }

            if ($product instanceof Product && $this->has('variants')) {
                $kept = array_column($this->input('variants', []), 'id');
                if ($product->variants()->whereNotIn('id', $kept)->whereHas('inventory', fn ($q) => $q->where('reserved_quantity', '>', 0))->exists()) {
                    $validator->errors()->add('variants', 'A variant with reserved stock cannot be removed. Hide it instead.');
                }
            }
        });
    }
}
