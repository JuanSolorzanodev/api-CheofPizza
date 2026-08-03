<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\Payment;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso HTTP para representar pedidos del cliente.
 *
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * Convierte el pedido en una estructura segura
     * y consistente para el frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'order_number' => (string) $this->order_number,

            'ordered_at' => $this->ordered_at?->toISOString(),

            'subtotal' => $this->resolveSubtotal(),

            'delivery_fee' => $this->money(
                $this->delivery_fee ?? 0,
            ),

            'total' => $this->money(
                $this->total ?? 0,
            ),

            'currency' => 'USD',

            'delivery_type' => $this->deliveryType?->delivery_type_name,

            'payment_method' => $this->paymentMethod?->name,

            'status' => $this->orderStatus?->status_name,

            'address' => $this->nullableString(
                $this->address,
            ),

            'delivery_location' => [
                'lat' => $this->delivery_lat !== null
                    ? (float) $this->delivery_lat
                    : null,

                'lng' => $this->delivery_lng !== null
                    ? (float) $this->delivery_lng
                    : null,

                'maps_url' => $this->nullableString(
                    $this->delivery_maps_url,
                ),

                'place_id' => $this->nullableString(
                    $this->delivery_place_id,
                ),

                'reference' => $this->nullableString(
                    $this->delivery_reference,
                ),
            ],

            'customer' => $this->whenLoaded(
                'user',
                fn(): ?array => $this->customerData(),
            ),

            'payment' => $this->whenLoaded(
                'latestPayment',
                fn(): ?array => $this->paymentData(),
            ),

            'items' => $this->whenLoaded(
                'orderItems',
                fn(): array => $this->itemsData(),
            ),
            'payment_receipt' =>
            $this->whenLoaded(
                'latestPaymentReceipt',
                fn(): mixed =>
                $this->latestPaymentReceipt === null
                    ? null
                    : new PaymentReceiptResource(
                        $this->latestPaymentReceipt,
                    ),
            ),

            'items_count' => $this->resolveItemsCount(),

            'status_changes' => $this->whenLoaded(
                'statusChanges',
                fn(): array => $this->statusChangesData(),
            ),

            'whatsapp_receipt_url' => $this->nullableString(
                $this->whatsapp_receipt_url,
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function customerData(): ?array
    {
        $user = $this->user;

        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user->id,

            'name' => $this->resolveUserName(
                $user,
            ),

            'email' => $this->nullableString(
                $user->email,
            ),

            'phone' => $this->nullableString(
                $user->phone
                    ?? $user->phone_number
                    ?? null,
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paymentData(): ?array
    {
        $payment = $this->latestPayment;

        if (! $payment instanceof Payment) {
            return null;
        }

        return [
            'id' => (int) $payment->id,

            'uuid' => $this->nullableString(
                $payment->uuid,
            ),

            'provider' => $this->enumValue(
                $payment->provider,
            ),

            'status' => $this->enumValue(
                $payment->status,
            ),

            'amount' => $this->money(
                $payment->amount ?? 0,
            ),

            'currency' => strtoupper(
                (string) ($payment->currency ?? 'USD'),
            ),

            'paypal_order_id' => $this->nullableString(
                $payment->paypal_order_id ?? null,
            ),

            'paypal_capture_id' => $this->nullableString(
                $payment->paypal_capture_id ?? null,
            ),

            'approved_at' => $payment->approved_at?->toISOString(),

            'paid_at' => $payment->paid_at?->toISOString(),

            'failed_at' => $payment->failed_at?->toISOString(),

            'cancelled_at' => $payment->cancelled_at?->toISOString(),

            'refunded_at' => $payment->refunded_at?->toISOString(),

            'created_at' => $payment->created_at?->toISOString(),

            'updated_at' => $payment->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsData(): array
    {
        return $this->orderItems
            ->map(function ($item): array {
                return [
                    'id' => (int) $item->id,

                    'item_type' => $item->promotion_id !== null
                        ? 'promotion'
                        : 'pizza',

                    'is_half_and_half' => (bool) $item->is_half_and_half,

                    'promotion' => $item->promotion_id !== null
                        ? [
                            'id' => (int) $item->promotion_id,
                            'name' => $item->promotion_name,
                        ]
                        : null,

                    'pizza' => $item->pizza_id !== null
                        ? [
                            'id' => (int) $item->pizza_id,
                            'name' => $item->pizza_name,
                            'category' => $item->category_name,
                        ]
                        : null,

                    'pizza_second' => $item->pizza_id_second !== null
                        ? [
                            'id' => (int) $item->pizza_id_second,
                            'name' => $item->pizza_name_second,
                            'category' => $item->category_name_second,
                        ]
                        : null,

                    'size' => $item->size_id !== null
                        ? [
                            'id' => (int) $item->size_id,
                            'name' => $item->size_name,
                        ]
                        : null,

                    'quantity' => (int) $item->quantity,

                    'unit_price' => $this->money(
                        $item->unit_price,
                    ),

                    'subtotal' => $this->money(
                        $item->subtotal,
                    ),

                    'selected_pizzas' => $this->promotionItemsData(
                        $item,
                    ),

                    'personalizations' => $this->personalizationsData(
                        $item,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function promotionItemsData(object $item): array
    {
        if (! $item->relationLoaded('orderPromotionItems')) {
            return [];
        }

        return $item->orderPromotionItems
            ->map(
                static fn($promotionItem): array => [
                    'id' => (int) $promotionItem->id,

                    'pizza_id' => $promotionItem->pizza_id !== null
                        ? (int) $promotionItem->pizza_id
                        : null,

                    'pizza_name' => $promotionItem->pizza_name,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function personalizationsData(object $item): array
    {
        if (! $item->relationLoaded('orderItemPersonalizations')) {
            return [];
        }

        return $item->orderItemPersonalizations
            ->map(function ($personalization): array {
                return [
                    'id' => (int) $personalization->id,

                    'order_promotion_item_id' =>
                    $personalization->order_promotion_item_id !== null
                        ? (int) $personalization->order_promotion_item_id
                        : null,

                    'ingredient_id' =>
                    $personalization->ingredient_id !== null
                        ? (int) $personalization->ingredient_id
                        : null,

                    'ingredient_name' =>
                    $personalization->ingredient_name,

                    'action_id' =>
                    $personalization->personalization_action_id !== null
                        ? (int) $personalization->personalization_action_id
                        : null,

                    'action' =>
                    $personalization
                        ->personalizationAction
                        ?->action_name
                        ?? $personalization
                        ->personalizationAction
                        ?->name,

                    'applies_to' =>
                    $personalization->applies_to,

                    'modification_type' =>
                    $personalization->modification_type,

                    'extra_price' =>
                    $this->money(
                        $personalization->extra_price ?? 0,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusChangesData(): array
    {
        return $this->statusChanges
            ->sortBy(
                static fn($change) =>
                $change->changed_at
                    ?? $change->created_at,
            )
            ->map(function ($change): array {
                return [
                    'id' => (int) $change->id,

                    'from_status' =>
                    $change->fromStatus?->status_name,

                    'to_status' =>
                    $change->toStatus?->status_name,

                    'note' =>
                    $this->nullableString(
                        $change->note,
                    ),

                    'changed_at' =>
                    $change->changed_at?->toISOString()
                        ?? $change->created_at?->toISOString(),

                    'changed_by' =>
                    $change->changedBy === null
                        ? null
                        : [
                            'id' => (int) $change->changedBy->id,

                            'name' => $this->resolveUserName(
                                $change->changedBy,
                            ),
                        ],
                ];
            })
            ->values()
            ->all();
    }

    private function resolveSubtotal(): float
    {
        if ($this->subtotal !== null) {
            return $this->money(
                $this->subtotal,
            );
        }

        if ($this->relationLoaded('orderItems')) {
            return $this->money(
                $this->orderItems->sum(
                    static fn($item): float =>
                    (float) ($item->subtotal ?? 0),
                ),
            );
        }

        return max(
            0,
            $this->money($this->total ?? 0)
                - $this->money($this->delivery_fee ?? 0),
        );
    }

    private function resolveItemsCount(): ?int
    {
        if (isset($this->order_items_count)) {
            return (int) $this->order_items_count;
        }

        if ($this->relationLoaded('orderItems')) {
            return (int) $this->orderItems->sum(
                static fn($item): int =>
                max(1, (int) ($item->quantity ?? 1)),
            );
        }

        return null;
    }

    private function resolveUserName(object $user): string
    {
        $name = trim(
            implode(
                ' ',
                array_filter([
                    $user->name ?? null,
                    $user->last_name ?? null,
                ]),
            ),
        );

        if ($name !== '') {
            return $name;
        }

        return (string) (
            $user->email
            ?? 'Usuario'
        );
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $this->nullableString(
            $value,
        );
    }

    private function money(mixed $value): float
    {
        return round(
            (float) ($value ?? 0),
            2,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value,
        );

        return $value !== ''
            ? $value
            : null;
    }
}
