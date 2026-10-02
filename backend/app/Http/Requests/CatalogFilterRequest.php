<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validated, whitelisted input for the public catalog listing.
 *
 * The endpoint is unauthenticated, so every value that reaches a query has to be
 * bounded here. Previously `perPage`, `page` and the featured `limit` were cast
 * straight from the query string, which allowed `perPage=-1` (a MySQL syntax
 * error surfaced as a 500) and `perPage=1000000` (whole-table dump).
 */
class CatalogFilterRequest extends FormRequest
{
    public const MAX_PER_PAGE = 48;

    public const SORTS = [
        'newest',
        'price-asc',
        'price-desc',
        'name-asc',
        'name-desc',
        'rating',
        'popularity',
        'featured',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:150'],
            'colors' => ['nullable'],
            'sizes' => ['nullable'],
            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    /**
     * Normalised filter set handed to the catalog service. Only keys the service
     * understands are returned, so a stray query parameter can never become a
     * filter by accident.
     */
    public function filters(): array
    {
        return [
            'q' => $this->input('q'),
            'category' => $this->input('category'),
            'brand' => $this->input('brand'),
            'colors' => $this->input('colors'),
            'sizes' => $this->input('sizes'),
            'min' => $this->input('min'),
            'max' => $this->input('max'),
            'rating' => $this->input('rating'),
            // boolean() so that "0" and "false" turn the filter off instead of on.
            'stock' => $this->boolean('stock'),
            'sort' => $this->input('sort', 'newest'),
            'page' => (int) $this->input('page', 1),
            'perPage' => (int) $this->input('perPage', 12),
        ];
    }
}