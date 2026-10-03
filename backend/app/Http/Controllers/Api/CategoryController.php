<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $categories)
    {
    }

    public function index()
    {
        return ['data' => Cache::remember(
            CategoryService::TREE_CACHE_KEY,
            86400,
            fn () => $this->categories->publicTree()
        )];
    }
}