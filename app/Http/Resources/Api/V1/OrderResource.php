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

            'payment_method' => $this->paymentMethod?->name,
            'status' => $this->orderStatus?->status_name,
             // ✅ NUEVO: link para enviar comprobante (solo transferencia)
            'whatsapp_receipt_url' => ($this->paymentMethod?->name === 'transfer')
                ? app(WhatsAppReceiptLinkService::class)->build($this->resource)
                : null,

            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
        ];
    }
}
