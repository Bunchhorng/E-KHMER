<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'parent_id' => $this->parent_id,
            // Absent unless the caller eager-loaded the count, which the admin
            // manager does because the tree view renders it.
            'products_count' => $this->whenCounted('products'),
            // Only serialised for a nested model response; the trees come from
            // CategoryService, which walks the hierarchy at any depth.
            'children' => $this->whenLoaded('children', fn () => self::collection($this->children->values())),
        ];
    }
}