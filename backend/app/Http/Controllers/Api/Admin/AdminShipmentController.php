<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminShipmentController extends Controller
{
    public function __construct(private ShipmentService $shipments)
    {
    }

    public function index(Request $request)
    {
        $query = Shipment::with(['order.user', 'method'])->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->q).'%';
            $query->where(function ($q) use ($term) {
                $q->where('tracking_number', 'like', $term)
                    ->orWhere('carrier', 'like', $term)
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', $term));
            });
        }

        $paginator = $query->paginate(15);

        return [
            'data' => ShipmentResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function show(Shipment $shipment)
    {
        return new ShipmentResource($shipment->load(['order.user', 'method']));
    }

    public function update(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in([
                Shipment::STATUS_PENDING,
                Shipment::STATUS_SHIPPED,
                Shipment::STATUS_IN_TRANSIT,
                Shipment::STATUS_DELIVERED,
                Shipment::STATUS_RETURNED,
            ])],
        ]);

        return new ShipmentResource($this->shipments->update($shipment, $data, $request->user()?->id));
    }
}
