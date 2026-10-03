<?php

namespace App\Http\Requests;

use App\Models\Category;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AdminCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStoring = $this->isMethod('post');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $this->assertNoHierarchyCycle($value, $fail);
                },
            ],
            // A partial update must not be forced to resend the name.
            'name' => [$isStoring ? 'required' : 'nullable', 'string', 'max:255'],
            // The slug is normalised to a web-safe alphabet in
            // prepareForValidation(), so the shape is guaranteed rather than rejected.
            'slug' => ['nullable', 'string', 'max:255'],
            // categories.description is a text column (65 535 bytes).
            'description' => ['nullable', 'string', 'max:60000'],
            'image' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            // categories.sort_order is an unsigned integer column.
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function prepareForValidation(): void
    {
        $hadSlug = $this->exists('slug');
        $slug = Str::slug(trim((string) $this->input('slug')));

        if ($slug === '' && $this->isMethod('post')) {
            // A new category derives its slug from the name.
            $slug = Str::slug((string) $this->input('name'));
        }

        if ($slug !== '') {
            $this->merge(['slug' => $slug]);
        } elseif ($hadSlug) {
            // A non-latin name can slugify to nothing; hand the decision to the
            // controller instead of failing the whole payload on the regex.
            $this->merge(['slug' => null]);
        }
    }

    /**
     * Reject a parent that is the category itself or one of its descendants.
     *
     * Without this the stored graph can contain a cycle, which makes the tree
     * unusable and the category unreachable from the storefront navigation.
     */
    protected function assertNoHierarchyCycle(mixed $parentId, Closure $fail): void
    {
        $category = $this->route('category');

        if (! $category instanceof Category || $parentId === null || $parentId === '') {
            return;
        }

        $parentId = (int) $parentId;

        if ($parentId === (int) $category->id) {
            $fail('A category cannot be its own parent.');

            return;
        }

        $seen = [$category->id => true];
        $cursor = $parentId;

        while ($cursor !== null) {
            if (isset($seen[$cursor])) {
                $fail('A category cannot be moved under one of its own sub-categories.');

                return;
            }

            $seen[$cursor] = true;

            try {
                $cursor = Category::query()->findOrFail($cursor)->parent_id;
            } catch (ModelNotFoundException) {
                return;
            }
        }
    }
}