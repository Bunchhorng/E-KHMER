<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function __construct(protected ReviewService $reviews)
    {
    }

    public function index(Request $request)
    {
        $query = Review::with(['user', 'product'])->orderByDesc('created_at');

        $shopIds = $this->visibleShopIds($request);

        if ($shopIds !== null) {
            $shopIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('shop_id', $shopIds);
        }

        if ($request->filled('shop_id') && $request->shop_id !== 'all') {
            $query->where('shop_id', (int) $request->shop_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $paginator = $query->paginate(15);

        return [
            'data' => ReviewResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function approve(Review $review)
    {
        $this->authorize('moderate', $review);

        $this->reviews->approve($review);

        return new ReviewResource($review->fresh(['user', 'product']));
    }

    public function reject(Review $review)
    {
        $this->authorize('moderate', $review);

        $this->reviews->reject($review);

        return new ReviewResource($review->fresh(['user', 'product']));
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return response()->json(['data' => ['message' => 'Review deleted successfully.']]);
    }

    private function visibleShopIds(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || $user->isAdmin()) {
            return null;
        }

        return $user->shops()
            ->wherePivot('status', 'active')
            ->pluck('shops.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
