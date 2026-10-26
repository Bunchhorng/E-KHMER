<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessExpenseRequest;
use App\Http\Resources\BusinessExpenseResource;
use App\Models\BusinessExpense;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BusinessExpenseController extends Controller
{
    private function scope(Request $request, bool $required = false): ?Shop
    {
        $shop = $request->route('shop');
        if (! $shop instanceof Shop) {
            $data = $request->validate(['shop_id' => [$required ? 'required' : 'nullable', 'integer', Rule::exists('shops', 'id')->whereNull('deleted_at')]]);
            $shop = isset($data['shop_id']) ? Shop::findOrFail($data['shop_id']) : null;
        }
        Gate::authorize('business-analytics', [$shop]);

        return $shop;
    }

    public function index(Request $request, ?Shop $shop = null): AnonymousResourceCollection
    {
        $shop = $this->scope($request);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'currency' => ['sometimes', Rule::in(['USD', 'KHR'])], 'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return BusinessExpenseResource::collection(BusinessExpense::query()->with('shop')
            ->when($shop, fn (Builder $query) => $query->where('shop_id', $shop->id))
            ->where('currency', $filters['currency'] ?? 'USD')
            ->when($filters['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('incurred_on', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, $date) => $query->whereDate('incurred_on', '<=', $date))
            ->orderByDesc('incurred_on')->orderByDesc('id')->paginate(20));
    }

    public function store(BusinessExpenseRequest $request, ?Shop $shop = null): BusinessExpenseResource
    {
        $shop = $this->scope($request, true);
        $expense = BusinessExpense::create([
            ...$request->safe()->only(['incurred_on', 'category', 'description', 'amount', 'currency']),
            'shop_id' => $shop->id, 'created_by' => $request->user()->id,
        ]);

        return new BusinessExpenseResource($expense->load('shop'));
    }

    public function update(BusinessExpenseRequest $request, ?Shop $shop = null): BusinessExpenseResource
    {
        $shop = $this->scope($request);
        $expense = BusinessExpense::query()->when($shop, fn (Builder $query) => $query->where('shop_id', $shop->id))->findOrFail($request->route('expense'));
        $expense->update($request->safe()->only(['incurred_on', 'category', 'description', 'amount', 'currency']));

        return new BusinessExpenseResource($expense->load('shop'));
    }

    public function destroy(Request $request, ?Shop $shop = null): Response
    {
        $shop = $this->scope($request);
        $expense = BusinessExpense::query()->when($shop, fn (Builder $query) => $query->where('shop_id', $shop->id))->findOrFail($request->route('expense'));
        $expense->delete();

        return response()->noContent();
    }
}
