<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        $deliveryType =
            $this->deliveryType
                ?->delivery_type_name;

        $hasDeliveryLocation =
            $deliveryType === 'delivery'
            && $this->delivery_lat !== null
            && $this->delivery_lng !== null;

        return [
            'id' =>
                (int) $this->id,

            'order_number' =>
                (string) $this->order_number,

            'ordered_at' =>
                $this->ordered_at
                    ?->toISOString(),

            'subtotal' =>
                (float) $this->subtotal,

            'delivery_fee' =>
                (float) $this->delivery_fee,

            'total' =>
                (float) $this->total,

            'delivery_type' =>
                $deliveryType,

            'address' =>
                $this->address,

            'delivery_location' =>
                $hasDeliveryLocation
                    ? [
                        'lat' =>
                            (float) $this->delivery_lat,

                        'lng' =>
                            (float) $this->delivery_lng,

                        'maps_url' =>
                            $this->delivery_maps_url,

                        'place_id' =>
                            $this->delivery_place_id,

                        'reference' =>
                            $this->delivery_reference,

                        'formatted_address' =>
                            $this->address,
                    ]
                    : null,

            'payment_method' =>
                $this->paymentMethod?->name,

            'status' =>
                $this->orderStatus?->status_name,

            'whatsapp_receipt_url' =>
                $this->paymentMethod?->name
                === 'transfer'
                    ? app(
                        \App\Services\Order\WhatsAppReceiptLinkService::class,
                    )->build(
                        $this->resource,
                    )
                    : null,

            'items' =>
                OrderItemResource::collection(
                    $this->whenLoaded(
                        'orderItems',
                    ),
                ),

            'status_changes' =>
                OrderStatusChangeResource::collection(
                    $this->whenLoaded(
                        'statusChanges',
                    ),
                ),
        ];
    }
}
