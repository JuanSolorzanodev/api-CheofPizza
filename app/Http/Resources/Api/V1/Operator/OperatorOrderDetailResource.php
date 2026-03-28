<?php

namespace App\Http\Resources\Api\V1\Operator;

use App\Services\Order\WhatsAppDeliveryDispatchLinkService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorOrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $o = $this->resource;

        return [
            'id' => (int)$o->id,
            'order_number' => (string)($o->order_number ?? ''),
            'ordered_at' => optional($o->ordered_at)->toIso8601String(),
            'total' => (float)($o->total ?? 0),

            'status' => (string)($o->orderStatus?->status_name ?? ''),
            'delivery_type' => (string)($o->deliveryType?->delivery_type_name ?? ''),
            'payment_method' => (string)($o->paymentMethod?->name ?? ''),

            'customer' => [
                'id' => (int)($o->user?->id ?? 0),
                'name' => trim(($o->user?->first_name ?? '') . ' ' . ($o->user?->last_name ?? '')),
                'phone' => (string)($o->user?->phone ?? ''),
                'email' => (string)($o->user?->email ?? ''),
            ],

            'delivery' => [
                'address' => (string)($o->address ?? ''),
                'lat' => $o->delivery_lat !== null ? (float)$o->delivery_lat : null,
                'lng' => $o->delivery_lng !== null ? (float)$o->delivery_lng : null,
                'maps_url' => (string)($o->delivery_maps_url ?? ''),
                'reference' => (string)($o->delivery_reference ?? ''),
            ],

            'delivery_whatsapp_url' => ($o->deliveryType?->delivery_type_name === 'delivery')
                ? app(WhatsAppDeliveryDispatchLinkService::class)->build($o)
                : null,

            'kitchen' => [
                'items' => $this->kitchenItems($o),
            ],

            'status_changes' => $o->relationLoaded('statusChanges')
                ? $o->statusChanges->map(fn($c) => [
                    'from' => (string)($c->fromStatus?->status_name ?? ''),
                    'to' => (string)($c->toStatus?->status_name ?? ''),
                    'changed_at' => optional($c->changed_at)->toIso8601String(),
                    'note' => (string)($c->note ?? ''),
                    'by' => trim(($c->changedBy?->first_name ?? '') . ' ' . ($c->changedBy?->last_name ?? '')) ?: 'Sistema',
                ])->values()->all()
                : [],
        ];
    }

    private function kitchenItems($o): array
    {
        if (!$o->relationLoaded('orderItems')) return [];

        return $o->orderItems->map(function ($it) {

            $base = [
                'id' => (int)$it->id,
                'quantity' => (int)($it->quantity ?? 1),
                'size_name' => (string)($it->size_name ?? ''),
                'category_name' => (string)($it->category_name ?? ''),
                'type' => 'pizza',
                'personalizations' => $this->personalizations($it),
            ];

            if (!empty($it->promotion_id)) {
                $pizzas = $it->relationLoaded('orderPromotionItems')
                    ? $it->orderPromotionItems->map(function ($pi) {
                        $pizza = $pi->pizza;

                        return [
                            'pizza_id' => (int)($pi->pizza_id ?? 0),
                            'pizza_name' => (string)($pi->pizza_name ?? ''),
                            'ingredients' => $this->extractPizzaIngredients($pizza),
                        ];
                    })->values()->all()
                    : [];

                return array_merge($base, [
                    'type' => 'promotion',
                    'promotion' => [
                        'id' => (int)$it->promotion_id,
                        'name' => (string)($it->promotion_name ?? ''),
                        'pizzas' => $pizzas,
                    ],
                ]);
            }

            if ((bool)$it->is_half_and_half) {
                $a = $it->pizza;
                $b = $it->pizzaSecond;

                return array_merge($base, [
                    'type' => 'half_and_half',
                    'half' => [
                        'A' => [
                            'pizza_id' => (int)($it->pizza_id ?? 0),
                            'pizza_name' => (string)($it->pizza_name ?? ''),
                            'ingredients' => $this->extractPizzaIngredients($a),
                        ],
                        'B' => [
                            'pizza_id' => (int)($it->pizza_id_second ?? 0),
                            'pizza_name' => (string)($it->pizza_name_second ?? ''),
                            'ingredients' => $this->extractPizzaIngredients($b),
                        ],
                    ],
                ]);
            }

            $pizza = $it->pizza;

            return array_merge($base, [
                'type' => 'pizza',
                'pizza' => [
                    'pizza_id' => (int)($it->pizza_id ?? 0),
                    'pizza_name' => (string)($it->pizza_name ?? ''),
                    'ingredients' => $this->extractPizzaIngredients($pizza),
                ],
            ]);
        })->values()->all();
    }

    private function extractPizzaIngredients($pizza): array
    {
        if (!$pizza) return [];

        if ($pizza->relationLoaded('ingredients') && $pizza->ingredients?->isNotEmpty()) {
            return $pizza->ingredients
                ->map(fn($ing) => trim((string)($ing->ingredient_name ?? '')))
                ->filter()
                ->values()
                ->all();
        }

        if ($pizza->relationLoaded('pizzaIngredients') && $pizza->pizzaIngredients?->isNotEmpty()) {
            return $pizza->pizzaIngredients
                ->map(fn($pi) => trim((string)($pi->ingredient?->ingredient_name ?? '')))
                ->filter()
                ->values()
                ->all();
        }

        $desc = trim((string)($pizza->description ?? ''));
        if ($desc !== '') {
            return collect(explode(',', $desc))
                ->map(fn($s) => trim($s))
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }

    private function personalizations($it): array
    {
        if (!$it->relationLoaded('orderItemPersonalizations')) return [];

        return $it->orderItemPersonalizations->map(fn($p) => [
            'ingredient_id' => (int)($p->ingredient_id ?? 0),
            'ingredient_name' => (string)($p->ingredient_name ?? ''),
            'action' => (string)($p->personalizationAction?->action_name ?? ''),
            'applies_to' => (string)($p->applies_to ?? 'ALL'),
            'extra_price' => (float)($p->extra_price ?? 0),
        ])->values()->all();
    }
}
