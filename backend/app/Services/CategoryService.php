<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryService
{
    /**
     * Cache key for the public storefront tree.
     */
    public const TREE_CACHE_KEY = 'categories:tree';

    /**
     * Nested tree for the storefront navigation.
     *
     * Inactive categories are dropped at the query level, which also prunes their
     * whole subtree: a child is only reachable through its parent in the tree.
     */
    public function publicTree(): array
    {
        return $this->buildTree($this->flatCategories(onlyActive: true));
    }

    /**
     * Nested tree for the admin manager.
     *
     * Draft categories are included on purpose — an admin has to be able to see,
     * edit and delete them. `products_count` is carried because the tree view
     * renders it as a chip.
     */
    public function adminTree(): array
    {
        return $this->buildTree($this->flatCategories(onlyActive: false));
    }

    /**
     * Category ids matching a storefront category slug, descendants included.
     *
     * Returns null when no slug was requested, and an empty array when the slug is
     * unknown so the caller filters on nothing instead of everything.
     *
     * @return array<int,int>|null
     */
    public function filterIdsForSlug(?string $slug): ?array
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return null;
        }

        $category = Category::query()->where('slug', $slug)->first();

        if (! $category) {
            return [];
        }

        return [$category->id, ...$this->descendantIds($category->id, $this->hierarchy()['parents'])];
    }

    /**
     * Sidebar facets whose count covers the category and everything beneath it,
     * so the number next to a filter agrees with the listing it produces.
     *
     * @return array<int,array{slug:string,name:string,count:int}>
     */
    public function facets(): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get();

        $all = $this->hierarchy();

        // A category whose parent is retired is unreachable from the storefront
        // navigation, so offering it as a filter would be a dead end.
        $reachable = $categories
            ->filter(fn (Category $category) => $this->ancestorsAreActive($category, $all))
            ->values();

        $directCounts = $reachable->mapWithKeys(fn (Category $c) => [$c->id => (int) $c->products_count])->all();
        $parents = $all['parents'];

        return $reachable
            ->map(function (Category $category) use ($directCounts, $parents) {
                $ids = [$category->id, ...$this->descendantIds($category->id, $parents)];

                return [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'count' => array_sum(array_intersect_key($directCounts, array_flip($ids))),
                ];
            })
            ->all();
    }

    public function flushTreeCache(): void
    {
        cache()->forget(self::TREE_CACHE_KEY);
    }

    /**
     * @return Collection<int,Category>
     */
    protected function flatCategories(bool $onlyActive): Collection
    {
        return Category::query()
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Assemble the nested tree from one flat query.
     *
     * The `$visited` set makes this terminate even if legacy rows already contain
     * a parent cycle, which a naive recursion would follow forever.
     *
     * @param  Collection<int,Category>  $categories
     * @return array<int,array<string,mixed>>
     */
    protected function buildTree(Collection $categories): array
    {
        // parent_id is nullable, so roots are grouped under a sentinel key.
        $byParent = $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $visited = [];

        $build = function (int $parentKey) use (&$build, $byParent, &$visited): array {
            $nodes = [];

            foreach ($byParent->get($parentKey, collect()) as $category) {
                if (isset($visited[$category->id])) {
                    continue;
                }

                $visited[$category->id] = true;

                $nodes[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image' => $category->image,
                    'sort_order' => (int) $category->sort_order,
                    'is_active' => (bool) $category->is_active,
                    'parent_id' => $category->parent_id,
                    'products_count' => (int) $category->products_count,
                    'children' => $build((int) $category->id),
                ];
            }

            return $nodes;
        };

        return $build(0);
    }

    /**
     * Ids of every category beneath `$id`, at any depth.
     *
     * @param  array<int,int|null>  $parents
     * @return array<int,int>
     */
    protected function descendantIds(int $id, array $parents): array
    {
        $childrenOf = [];

        foreach ($parents as $childId => $parentId) {
            if ($parentId !== null) {
                $childrenOf[(int) $parentId][] = (int) $childId;
            }
        }

        $ids = [];
        $seen = [$id => true];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenOf[$current] ?? [] as $childId) {
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @param  array<int,int|null>  $parents
     */
    protected function ancestorsAreActive(Category $category, array $hierarchy): bool
    {
        $active = $hierarchy['active'];
        $parents = $hierarchy['parents'];

        $seen = [$category->id => true];
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parentId = (int) $parentId;

            if (isset($seen[$parentId]) || ! isset($active[$parentId])) {
                return false;
            }

            $seen[$parentId] = true;
            $parentId = $parents[$parentId] ?? null;
        }

        return true;
    }

    /**
     * Whole-category hierarchy in a single query: the parent map plus the set of
     * active ids, both needed to walk the tree without an N+1.
     *
     * @return array{parents:array<int,int|null>,active:array<int,true>}
     */
    protected function hierarchy(): array
    {
        $rows = Category::query()->get(['id', 'parent_id', 'is_active']);

        return [
            'parents' => $rows->mapWithKeys(fn (Category $c) => [$c->id => $c->parent_id])->all(),
            'active' => $rows
                ->filter(fn (Category $c) => (bool) $c->is_active)
                ->mapWithKeys(fn (Category $c) => [$c->id => true])
                ->all(),
        ];
    }
}