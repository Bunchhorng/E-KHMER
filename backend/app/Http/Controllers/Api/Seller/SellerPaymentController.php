<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\CodPaymentResource;
use App\Models\BakongPayment;
use App\Models\PaymentRefund;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Services\CodCollectionService;
use App\Services\RefundService;
use Illuminate\Http\Request;

class SellerPaymentController extends Controller
{
    public function index(Request $request, Shop $shop)
    {
        if ($request->query('method') === 'cod') {
            return CodPaymentResource::collection(ShopOrder::with(['order.payment', 'shop', 'shipment', 'codCollection'])
                ->where('shop_id', $shop->id)->whereHas('order.payment', fn ($query) => $query->where('method', 'cod'))->latest()->paginate(15));
        }
        $page = BakongPayment::with('order')->where('shop_id', $shop->id)->latest()->paginate(15);

        return response()->json(['data' => $page->getCollection()->map(fn ($p) => ['id' => $p->id, 'order_number' => $p->order?->order_number,
            'amount' => (float) $p->amount, 'currency' => $p->currency, 'status' => $p->status, 'transaction_hash' => $p->transaction_hash,
            'refund_memo' => RefundService::memo($p->order),
            'refunded_amount' => (float) PaymentRefund::where('bakong_payment_id', $p->id)->sum('amount'), 'paid_at' => $p->paid_at?->toISOString()]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function collectCod(Request $request, Shop $shop, ShopOrder $shopOrder, CodCollectionService $collections)
    {
        abort_unless((int) $shopOrder->shop_id === (int) $shop->id, 404);

        return new CodPaymentResource($collections->collect($shopOrder, $request->user()));
    }

    public function refund(Request $request, Shop $shop, BakongPayment $bakongPayment, RefundService $refunds)
    {
        abort_unless((int) $bakongPayment->shop_id === (int) $shop->id, 404);
        abort_unless($bakongPayment->status === 'refund_required', 422, 'Use the return workflow for delivered items.');
        $data = $request->validate(['reference' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/']]);
        $remaining = max(0, (float) $bakongPayment->amount - (float) PaymentRefund::where('bakong_payment_id', $bakongPayment->id)->sum('amount'));
        $refunds->record($bakongPayment->order, $bakongPayment, number_format($remaining, 2, '.', ''), $data['reference'], $request->user()->id);

        return response()->json(['data' => ['message' => 'Refund verified.']]);
    }
}
