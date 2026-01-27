<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Order\WhatsAppReceiptLinkService;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
return [
    'id' => $this->id,
    'order_number' => $this->order_number,
    'ordered_at' => optional($this->ordered_at)->toISOString(),
    'total' => (float) $this->total,

    'delivery_type' => $this->deliveryType?->delivery_type_name,
    'address' => $this->address,

    'delivery_location' => ($this->deliveryType?->delivery_type_name === 'delivery' && $this->delivery_lat && $this->delivery_lng)
        ? [
            'lat' => (float) $this->delivery_lat,
            'lng' => (float) $this->delivery_lng,
            'maps_url' => $this->delivery_maps_url,
            'place_id' => $this->delivery_place_id,
            'reference' => $this->delivery_reference,
            'formatted_address' => $this->address,
        ]
        : null,

    'payment_method' => $this->paymentMethod?->name,
    'status' => $this->orderStatus?->status_name,

    'whatsapp_receipt_url' => ($this->paymentMethod?->name === 'transfer')
        ? app(\App\Services\Order\WhatsAppReceiptLinkService::class)->build($this->resource)
        : null,

    'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
];

    }
}
