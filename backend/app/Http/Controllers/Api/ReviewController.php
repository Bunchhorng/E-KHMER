<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewStoreRequest;
use App\Http\Requests\ReviewUpdateRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviews)
    {
    }

    public function index(Request $request, int $product)
    {
        // Reviews of an unpublished product are not public, otherwise the review
        // endpoint stays a way to read a product the catalog hides.
        abort_unless(Product::query()->active()->whereKey($product)->exists(), 404, 'Product not found.');

        $reviews = Review::with(['user'])
            ->where('product_id', $product)
            ->where('status', Review::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->get();

        return ReviewResource::collection($reviews);
    }

    public function store(ReviewStoreRequest $request)
    {
        $review = $this->reviews->store($request->user(), $request->validated());

        return (new ReviewResource($review))->response()->setStatusCode(201);
    }

    public function update(ReviewUpdateRequest $request, Review $review)
    {
        $review = $this->reviews->update($request->user(), $review, $request->validated());

        return new ReviewResource($review);
    }
}
