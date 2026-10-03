<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    public function __construct(protected CategoryService $categories)
    {
    }

    public function index(Request $request)
    {
        return response()->json(['data' => $this->categories->adminTree()]);
    }

    public function store(AdminCategoryRequest $request)
    {
        $data = $request->validated();

        // categories.slug has a unique index and the payload may repeat a slug that
        // is already taken, so it has to be de-duplicated before the insert.
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name'] ?? null);

        $category = Category::create($data);

        // The is_active/sort_order defaults live in the database, so the model has to
        // be re-read before it is serialised or the response reports the wrong state.
        $category->refresh();

        $this->categories->flushTreeCache();

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(AdminCategoryRequest $request, Category $category)
    {
        $data = $request->validated();

        if (isset($data['slug']) && $data['slug'] !== $category->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], null, $category->id);
        }

        $category->fill($data)->save();
        $category->refresh();

        $this->categories->flushTreeCache();

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        if ($category->children()->exists()) {
            return response()->json([
                'data' => ['message' => 'Contains sub-categories; delete those first.'],
            ], 422);
        }

        // products.category_id is nullOnDelete, so deleting a populated category
        // would silently orphan its products. Block instead of losing the link.
        if ($productCount = $category->products()->count()) {
            $noun = $productCount === 1 ? 'product' : 'products';

            return response()->json([
                'data' => ['message' => "Move its {$productCount} {$noun} to another category first."],
            ], 422);
        }

        $category->delete();

        $this->categories->flushTreeCache();

        return response()->json(['data' => ['message' => 'Category deleted.']]);
    }

    protected function uniqueSlug(?string $slug, ?string $name, ?int $ignoreId = null): string
    {
        $base = $slug ?: Str::slug((string) $name);
        $candidate = $base;
        $suffix = 1;

        while (Category::query()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}