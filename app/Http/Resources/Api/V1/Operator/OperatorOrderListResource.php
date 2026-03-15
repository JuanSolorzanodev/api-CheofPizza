<?php

namespace App\Http\Resources\Api\V1\Operator;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $o = $this->resource;

        return [
            'id' => (int) $o->id,
            'order_number' => (string)($o->order_number ?? ''),
            'ordered_at' => optional($o->ordered_at)->toIso8601String(),
            'total' => (float)($o->total ?? 0),

            'status' => (string)($o->orderStatus?->status_name ?? ''),
            'delivery_type' => (string)($o->deliveryType?->delivery_type_name ?? ''),
            'payment_method' => (string)($o->paymentMethod?->name ?? ''),

            'customer' => [
                'name' => trim(($o->user?->first_name ?? '') . ' ' . ($o->user?->last_name ?? '')),
                'phone' => (string)($o->user?->phone ?? ''),
            ],

            // resumen para lista (no redundante)
            'kitchen_summary' => $this->kitchenSummary($o),
        ];
    }

    private function kitchenSummary($o): string
    {
        if (!$o->relationLoaded('orderItems')) return '';

        $parts = [];

        foreach ($o->orderItems as $it) {
            $qty = (int)($it->quantity ?? 1);
            $size = (string)($it->size_name ?? '');

            // Promo
            if (!empty($it->promotion_id)) {
                $pizzas = $it->relationLoaded('orderPromotionItems')
                    ? $it->orderPromotionItems->pluck('pizza_name')->filter()->values()->all()
                    : [];

                $desc = 'Promo ' . ($it->promotion_name ?? '');
                if ($pizzas) $desc .= ': ' . implode(', ', $pizzas);

                $parts[] = "{$qty}x {$size} {$desc}";
                continue;
            }

            // Mitad y mitad
            if ((bool)$it->is_half_and_half) {
                $a = (string)($it->pizza_name ?? '');
                $b = (string)($it->pizza_name_second ?? '');
                $parts[] = "{$qty}x {$size} Mitad {$a} / {$b}";
                continue;
            }

            // Normal
            $name = (string)($it->pizza_name ?? '');
            $parts[] = "{$qty}x {$size} {$name}";
        }

        return implode(' • ', $parts);
    }
}
