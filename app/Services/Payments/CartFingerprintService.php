<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Cart;

final class CartFingerprintService
{
    public function generate(Cart $cart): string
    {
        $cart->loadMissing([
            'cartItems.cartPromotionItems',
            'cartItems.cartItemPersonalizations',
        ]);

        $items = $cart->cartItems
            ->sortBy('id')
            ->map(function ($item): array {
                $promotionItems = $item
                    ->cartPromotionItems
                    ->sortBy('id')
                    ->map(fn ($promotionItem): array => [
                        'id' =>
                            (int) $promotionItem->id,

                        'pizza_id' =>
                            (int) $promotionItem->pizza_id,
                    ])
                    ->values()
                    ->all();

                $personalizations = $item
                    ->cartItemPersonalizations
                    ->sortBy('id')
                    ->map(fn ($personalization): array => [
                        'id' =>
                            (int) $personalization->id,

                        'promotion_item_id' =>
                            $personalization
                                ->cart_promotion_item_id
                                !== null
                                    ? (int) $personalization
                                        ->cart_promotion_item_id
                                    : null,

                        'ingredient_id' =>
                            (int) $personalization
                                ->ingredient_id,

                        'action_id' =>
                            (int) $personalization
                                ->personalization_action_id,

                        'applies_to' =>
                            (string) (
                                $personalization
                                    ->applies_to
                                ?? 'ALL'
                            ),

                        'extra_price' =>
                            $this->money(
                                $personalization
                                    ->extra_price
                            ),
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => (int) $item->id,

                    'item_type' =>
                        (string) $item->item_type,

                    'pizza_id' =>
                        $item->pizza_id !== null
                            ? (int) $item->pizza_id
                            : null,

                    'pizza_id_second' =>
                        $item->pizza_id_second !== null
                            ? (int) $item
                                ->pizza_id_second
                            : null,

                    'promotion_id' =>
                        $item->promotion_id !== null
                            ? (int) $item->promotion_id
                            : null,

                    'size_id' =>
                        $item->size_id !== null
                            ? (int) $item->size_id
                            : null,

                    'is_half_and_half' =>
                        (bool) $item
                            ->is_half_and_half,

                    'quantity' =>
                        (int) $item->quantity,

                    'unit_price' =>
                        $this->money(
                            $item->unit_price
                        ),

                    'subtotal' =>
                        $this->money(
                            $item->subtotal
                        ),

                    'promotion_items' =>
                        $promotionItems,

                    'personalizations' =>
                        $personalizations,
                ];
            })
            ->values()
            ->all();

        $payload = [
            'cart_id' => (int) $cart->id,
            'user_id' => $cart->user_id !== null
                ? (int) $cart->user_id
                : null,
            'total' => $this->money(
                $cart->cartItems->sum(
                    fn ($item): float =>
                        (float) $item->subtotal
                )
            ),
            'items' => $items,
        ];

        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION,
            )
        );
    }

    private function money(
        mixed $value
    ): string {
        return number_format(
            (float) $value,
            2,
            '.',
            '',
        );
    }
}
