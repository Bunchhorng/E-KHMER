<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource,
            'recent_orders' => $this->resource['recent_orders']->map(fn ($order) => [
                ...(new OrderListResource($order))->toArray($request),
                'currency' => $order->currency,
                'customer_name' => $order->customer_name,
                'email' => $order->email,
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
            ])->all(),
        ];
    }
}
