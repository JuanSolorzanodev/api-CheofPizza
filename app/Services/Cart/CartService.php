<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Ingredient;
use App\Models\PersonalizationAction;
use App\Services\Builder\BuilderQuoteService;
use App\Services\Promotion\PublicPromotionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class CartService
{
    public function __construct(
        private readonly BuilderQuoteService $quoteService,
        private readonly PublicPromotionService $promotionService
    ) {}

    public function getOrCreateActiveCart(?int $userId, ?string $sessionId): Cart
    {
        $activeStatusId = $this->activeStatusIdOrFail();
        $sessionId = $sessionId ?: $this->newSessionId();

        if ($userId) {
            $userCart = Cart::where('user_id', $userId)
                ->where('cart_status_id', $activeStatusId)
                ->latest('id')
                ->first();

            if (!$userCart) {
                $userCart = Cart::create([
                    'user_id' => $userId,
                    'cart_status_id' => $activeStatusId,
                    'session_id' => $sessionId,
                    'total' => 0,
                ]);
            } elseif (!$userCart->session_id) {
                $userCart->session_id = $sessionId;
                $userCart->save();
            }

            $guestCart = Cart::whereNull('user_id')
                ->where('session_id', $sessionId)
                ->where('cart_status_id', $activeStatusId)
                ->latest('id')
                ->first();

            if ($guestCart && $guestCart->id !== $userCart->id) {
                $this->mergeGuestCartIntoUserCart($guestCart, $userCart);
            }

            return $this->loadCart($userCart);
        }

        $cart = Cart::whereNull('user_id')
            ->where('session_id', $sessionId)
            ->where('cart_status_id', $activeStatusId)
            ->latest('id')
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'user_id' => null,
                'cart_status_id' => $activeStatusId,
                'session_id' => $sessionId,
                'total' => 0,
            ]);
        }

        return $this->loadCart($cart);
    }

    public function addPizza(Cart $cart, array $payload): Cart
    {
        return $cart->getConnection()->transaction(function () use ($cart, $payload) {
            $cart = $this->loadCart($cart);

            $payload = $this->normalizePizzaPayload($payload);
            $quote = $this->quoteService->quote($payload);

            $qty = (int) ($payload['quantity'] ?? 1);
            $sizeId = (int) ($payload['size_id'] ?? 0);

            $isHalf = (bool) ($payload['is_half_and_half'] ?? false);
            $pizzaAId = (int) ($payload['pizza_id'] ?? 0);
            $pizzaBId = $isHalf ? (int) ($payload['second_pizza_id'] ?? 0) : null;

            $unitPrice = (float) ($quote['unit_price'] ?? 0);
            $subTotal = round($unitPrice * $qty, 2);

            $customizations = $this->normalizeCustomizations($payload['customizations'] ?? []);

            $existing = $this->findEquivalentPizzaItemInLoadedCart(
                cart: $cart,
                pizzaAId: $pizzaAId,
                pizzaBId: $pizzaBId,
                isHalf: $isHalf,
                sizeId: $sizeId,
                customizations: $customizations
            );

            if ($existing) {
                $existing->quantity = (int) $existing->quantity + $qty;
                $existing->unit_price = $unitPrice;
                $existing->subtotal = round(((float) $existing->quantity) * $unitPrice, 2);
                $existing->save();

                $this->recalculateCartTotal($cart);
                return $this->loadCart($cart);
            }

            /** @var CartItem $item */
            $item = $cart->cartItems()->create([
                'item_type' => 'pizza',
                'is_half_and_half' => $isHalf,
                'pizza_id' => $pizzaAId,
                'pizza_id_second' => $pizzaBId,
                'promotion_id' => null,
                'size_id' => $sizeId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subTotal,
            ]);

            $breakdown = collect($quote['customizations_breakdown'] ?? [])
                ->keyBy(fn ($x) => strtolower(($x['action'] ?? 'extra')) . '|' . ((int) $x['ingredient_id']) . '|' . ((string) ($x['applies_to'] ?? 'ALL')));

            foreach ($customizations as $customization) {
                $actionName = $customization['action'] === 'remove' ? 'Quitar' : 'Extra';
                $actionId = $this->personalizationActionIdOrFail($actionName);

                $key = strtolower($customization['action']) . '|' . $customization['ingredient_id'] . '|' . $customization['applies_to'];
                $line = $breakdown->get($key);

                $extraPrice = (float) ($line['line_total'] ?? 0);

                $item->cartItemPersonalizations()->create([
                    'ingredient_id' => $customization['ingredient_id'],
                    'personalization_action_id' => $actionId,
                    'applies_to' => $customization['applies_to'],
                    'extra_price' => $extraPrice,
                    'cart_promotion_item_id' => null,
                ]);
            }

            $this->recalculateCartTotal($cart);
            return $this->loadCart($cart);
        });
    }

    public function addPromotion(Cart $cart, array $payload): Cart
    {
        return $cart->getConnection()->transaction(function () use ($cart, $payload) {
            $cart = $this->loadCart($cart);

            $promotion = $this->promotionService->findActiveByIdOrFail((int) $payload['promotion_id']);

            $selectedItemsPayload = $payload['selected_items'] ?? [];
            if (empty($selectedItemsPayload) && !empty($payload['selected_pizza_ids'])) {
                $selectedItemsPayload = collect($payload['selected_pizza_ids'])
                    ->map(fn ($pizzaId) => [
                        'pizza_id' => (int) $pizzaId,
                        'customizations' => [],
                    ])
                    ->values()
                    ->all();
            }

            $validatedSelection = $this->promotionService->validateSelectedItemsForPromotion(
                $promotion,
                $selectedItemsPayload
            );

            $quantity = (int) ($payload['quantity'] ?? 1);
            $sizeId = (int) $validatedSelection['size_id'];
            $selectedItems = collect($validatedSelection['selected_items'])->values();

            $ingredientIds = $selectedItems
                ->flatMap(fn ($row) => collect($row['customizations'] ?? [])->pluck('ingredient_id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $ingredients = Ingredient::query()
                ->with(['sizes' => fn ($q) => $q->where('sizes.id', $sizeId)])
                ->whereIn('id', $ingredientIds)
                ->get()
                ->keyBy('id');

            $promotionExtrasTotal = 0.0;

            foreach ($selectedItems as $row) {
                foreach (($row['customizations'] ?? []) as $customization) {
                    if (($customization['action'] ?? null) !== 'extra') {
                        continue;
                    }

                    $ingredient = $ingredients->get((int) $customization['ingredient_id']);
                    $pivot = $ingredient?->sizes?->first()?->pivot;
                    $promotionExtrasTotal += (float) ($pivot?->extra_price ?? 0);
                }
            }

            $promotionExtrasTotal = round($promotionExtrasTotal, 2);

            $unitPrice = round((float) $promotion->promotion_price + $promotionExtrasTotal, 2);
            $subTotal = round($unitPrice * $quantity, 2);

            $existing = $this->findEquivalentPromotionItemInLoadedCart(
                cart: $cart,
                promotionId: (int) $promotion->id,
                sizeId: $sizeId,
                selectedItems: $selectedItems->map(function ($row) {
                    return [
                        'pizza_id' => (int) $row['pizza']->id,
                        'customizations' => $this->normalizeCustomizations($row['customizations'] ?? []),
                    ];
                })->all()
            );

            if ($existing) {
                $existing->quantity = (int) $existing->quantity + $quantity;
                $existing->unit_price = $unitPrice;
                $existing->subtotal = round(((float) $existing->quantity) * $unitPrice, 2);
                $existing->save();

                $this->recalculateCartTotal($cart);
                return $this->loadCart($cart);
            }

            /** @var CartItem $item */
            $item = $cart->cartItems()->create([
                'item_type' => 'promotion',
                'is_half_and_half' => false,
                'pizza_id' => null,
                'pizza_id_second' => null,
                'promotion_id' => (int) $promotion->id,
                'size_id' => $sizeId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subTotal,
            ]);

            foreach ($selectedItems as $row) {
                $pizza = $row['pizza'];
                $customizations = $this->normalizeCustomizations($row['customizations'] ?? []);

                $promoItem = $item->cartPromotionItems()->create([
                    'pizza_id' => (int) $pizza->id,
                ]);

                foreach ($customizations as $customization) {
                    $actionName = $customization['action'] === 'remove' ? 'Quitar' : 'Extra';
                    $actionId = $this->personalizationActionIdOrFail($actionName);

                    $ingredient = $ingredients->get((int) $customization['ingredient_id']);
                    $pivot = $ingredient?->sizes?->first()?->pivot;
                    $extraPrice = $customization['action'] === 'extra'
                        ? (float) ($pivot?->extra_price ?? 0)
                        : 0.0;

                    $item->cartItemPersonalizations()->create([
                        'cart_promotion_item_id' => $promoItem->id,
                        'ingredient_id' => (int) $customization['ingredient_id'],
                        'personalization_action_id' => $actionId,
                        'applies_to' => 'ALL',
                        'extra_price' => round($extraPrice, 2),
                    ]);
                }
            }

            $this->recalculateCartTotal($cart);
            return $this->loadCart($cart);
        });
    }

    public function updateQuantity(Cart $cart, int $cartItemId, int $quantity): Cart
    {
        return $cart->getConnection()->transaction(function () use ($cart, $cartItemId, $quantity) {
            $cart = $this->loadCart($cart);

            /** @var CartItem|null $item */
            $item = $cart->cartItems->firstWhere('id', $cartItemId);

            if (!$item) {
                throw (new ModelNotFoundException())->setModel(CartItem::class, [$cartItemId]);
            }

            $item->quantity = $quantity;
            $item->subtotal = round(((float) $item->unit_price) * $quantity, 2);
            $item->save();

            $this->recalculateCartTotal($cart);
            return $this->loadCart($cart);
        });
    }

    public function removeItem(Cart $cart, int $cartItemId): Cart
    {
        return $cart->getConnection()->transaction(function () use ($cart, $cartItemId) {
            $cart = $this->loadCart($cart);

            /** @var CartItem|null $item */
            $item = $cart->cartItems->firstWhere('id', $cartItemId);

            if (!$item) {
                throw (new ModelNotFoundException())->setModel(CartItem::class, [$cartItemId]);
            }

            $item->delete();

            $this->recalculateCartTotal($cart);
            return $this->loadCart($cart);
        });
    }

    public function clear(Cart $cart): Cart
    {
        return $cart->getConnection()->transaction(function () use ($cart) {
            $cart = $this->loadCart($cart);

            $cart->cartItems->each->delete();
            $cart->total = 0;
            $cart->save();

            return $this->loadCart($cart);
        });
    }

    private function loadCart(Cart $cart): Cart
    {
        $relations = [
            'cartStatus',
            'cartItems.pizza.category',
            'cartItems.pizzaSecond.category',
            'cartItems.promotion.promotionDetails.category',
            'cartItems.promotion.promotionDetails.size',
            'cartItems.size',
            'cartItems.cartPromotionItems.pizza.category',
            'cartItems.cartItemPersonalizations.ingredient',
            'cartItems.cartItemPersonalizations.personalizationAction',
        ];

        $cart->load($relations);

        return $cart->fresh($relations) ?? $cart;
    }

    private function recalculateCartTotal(Cart $cart): void
    {
        $total = (float) $cart->cartItems()->sum('subtotal');

        $cart->forceFill([
            'total' => round($total, 2),
        ])->save();
    }

    private function activeStatusIdOrFail(): int
    {
        $status = CartStatus::where('status_name', 'active')->first();

        if (!$status) {
            throw new RuntimeException("CartStatus 'active' no existe. Revisa seeders.");
        }

        return (int) $status->id;
    }

    private function personalizationActionIdOrFail(string $actionName): int
    {
        $action = PersonalizationAction::where('action_name', $actionName)->first();

        if (!$action) {
            throw new RuntimeException("PersonalizationAction '{$actionName}' no existe. Revisa seeders.");
        }

        return (int) $action->id;
    }

    private function newSessionId(): string
    {
        return Str::uuid()->toString();
    }

    private function normalizePizzaPayload(array $payload): array
    {
        $customizations = $payload['customizations'] ?? null;

        if ((!is_array($customizations) || empty($customizations)) && is_array($payload['extras'] ?? null)) {
            $customizations = collect($payload['extras'])
                ->map(fn ($extra) => [
                    'action' => 'extra',
                    'ingredient_id' => $extra['ingredient_id'] ?? null,
                    'applies_to' => $extra['applies_to'] ?? 'ALL',
                ])
                ->values()
                ->all();
        }

        $payload['customizations'] = $customizations ?? [];
        return $payload;
    }

    private function normalizeCustomizations(array $customizations): array
    {
        return collect($customizations)
            ->map(fn ($c) => [
                'action' => strtolower((string) ($c['action'] ?? 'extra')),
                'ingredient_id' => (int) ($c['ingredient_id'] ?? 0),
                'applies_to' => (string) ($c['applies_to'] ?? 'ALL'),
            ])
            ->filter(fn ($c) => in_array($c['action'], ['extra', 'remove'], true) && $c['ingredient_id'] > 0)
            ->sortBy([['action', 'asc'], ['ingredient_id', 'asc'], ['applies_to', 'asc']])
            ->values()
            ->all();
    }

    private function findEquivalentPromotionItemInLoadedCart(
        Cart $cart,
        int $promotionId,
        int $sizeId,
        array $selectedItems
    ): ?CartItem {
        $wanted = collect($selectedItems)
            ->map(fn ($row) => [
                'pizza_id' => (int) $row['pizza_id'],
                'customizations' => $this->normalizeCustomizations($row['customizations'] ?? []),
            ])
            ->sortBy('pizza_id')
            ->values()
            ->all();

        $candidates = $cart->cartItems
            ->where('item_type', 'promotion')
            ->where('promotion_id', $promotionId)
            ->where('size_id', $sizeId)
            ->values();

        foreach ($candidates as $item) {
            $have = $item->cartPromotionItems
                ->map(function ($promoItem) use ($item) {
                    $customizations = $item->cartItemPersonalizations
                        ->where('cart_promotion_item_id', $promoItem->id)
                        ->map(fn ($p) => [
                            'action' => strtolower((string) $p->personalizationAction?->action_name) === 'quitar' ? 'remove' : 'extra',
                            'ingredient_id' => (int) $p->ingredient_id,
                            'applies_to' => 'ALL',
                        ])
                        ->sortBy([['action', 'asc'], ['ingredient_id', 'asc'], ['applies_to', 'asc']])
                        ->values()
                        ->all();

                    return [
                        'pizza_id' => (int) $promoItem->pizza_id,
                        'customizations' => $customizations,
                    ];
                })
                ->sortBy('pizza_id')
                ->values()
                ->all();

            if ($have === $wanted) {
                return $item;
            }
        }

        return null;
    }

    private function findEquivalentPizzaItemInLoadedCart(
        Cart $cart,
        int $pizzaAId,
        ?int $pizzaBId,
        bool $isHalf,
        int $sizeId,
        array $customizations
    ): ?CartItem {
        $wanted = $this->normalizeCustomizations($customizations);

        /** @var Collection<int, CartItem> $candidates */
        $candidates = $cart->cartItems
            ->where('item_type', 'pizza')
            ->where('pizza_id', $pizzaAId)
            ->where('size_id', $sizeId)
            ->where('is_half_and_half', $isHalf)
            ->values();

        if ($isHalf) {
            $candidates = $candidates->where('pizza_id_second', $pizzaBId)->values();
        } else {
            $candidates = $candidates->filter(fn (CartItem $i) => $i->pizza_id_second === null)->values();
        }

        foreach ($candidates as $item) {
            $have = $item->cartItemPersonalizations
                ->whereNull('cart_promotion_item_id')
                ->map(fn ($p) => [
                    'action' => strtolower((string) $p->personalizationAction?->action_name) === 'quitar' ? 'remove' : 'extra',
                    'ingredient_id' => (int) $p->ingredient_id,
                    'applies_to' => (string) $p->applies_to,
                ])
                ->sortBy([['action', 'asc'], ['ingredient_id', 'asc'], ['applies_to', 'asc']])
                ->values()
                ->all();

            if ($have === $wanted) {
                return $item;
            }
        }

        return null;
    }

    private function mergeGuestCartIntoUserCart(Cart $guestCart, Cart $userCart): void
    {
        $guestCart = $this->loadCart($guestCart);
        $userCart = $this->loadCart($userCart);

        foreach ($guestCart->cartItems as $guestItem) {
            if ($guestItem->item_type === 'promotion') {
                $selectedItems = $guestItem->cartPromotionItems
                    ->map(function ($promoItem) use ($guestItem) {
                        $customizations = $guestItem->cartItemPersonalizations
                            ->where('cart_promotion_item_id', $promoItem->id)
                            ->map(fn ($p) => [
                                'action' => strtolower((string) $p->personalizationAction?->action_name) === 'quitar' ? 'remove' : 'extra',
                                'ingredient_id' => (int) $p->ingredient_id,
                                'applies_to' => 'ALL',
                            ])
                            ->values()
                            ->all();

                        return [
                            'pizza_id' => (int) $promoItem->pizza_id,
                            'customizations' => $customizations,
                        ];
                    })
                    ->values()
                    ->all();

                $existingPromotion = $this->findEquivalentPromotionItemInLoadedCart(
                    cart: $userCart,
                    promotionId: (int) $guestItem->promotion_id,
                    sizeId: (int) $guestItem->size_id,
                    selectedItems: $selectedItems
                );

                if ($existingPromotion) {
                    $existingPromotion->quantity = (int) $existingPromotion->quantity + (int) $guestItem->quantity;
                    $existingPromotion->unit_price = (float) $guestItem->unit_price;
                    $existingPromotion->subtotal = round(((float) $existingPromotion->quantity) * (float) $existingPromotion->unit_price, 2);
                    $existingPromotion->save();
                    $guestItem->delete();
                } else {
                    $guestItem->cart_id = $userCart->id;
                    $guestItem->save();
                }

                $userCart = $this->loadCart($userCart);
                continue;
            }

            $customizations = $guestItem->cartItemPersonalizations
                ->whereNull('cart_promotion_item_id')
                ->map(fn ($p) => [
                    'action' => strtolower((string) $p->personalizationAction?->action_name) === 'quitar' ? 'remove' : 'extra',
                    'ingredient_id' => (int) $p->ingredient_id,
                    'applies_to' => (string) $p->applies_to,
                ])
                ->values()
                ->all();

            $existing = $this->findEquivalentPizzaItemInLoadedCart(
                cart: $userCart,
                pizzaAId: (int) $guestItem->pizza_id,
                pizzaBId: $guestItem->is_half_and_half ? (int) $guestItem->pizza_id_second : null,
                isHalf: (bool) $guestItem->is_half_and_half,
                sizeId: (int) $guestItem->size_id,
                customizations: $customizations
            );

            if ($existing) {
                $existing->quantity = (int) $existing->quantity + (int) $guestItem->quantity;
                $existing->unit_price = (float) $guestItem->unit_price;
                $existing->subtotal = round(((float) $existing->quantity) * (float) $existing->unit_price, 2);
                $existing->save();
                $guestItem->delete();
            } else {
                $guestItem->cart_id = $userCart->id;
                $guestItem->save();
            }

            $userCart = $this->loadCart($userCart);
        }

        $this->recalculateCartTotal($userCart);
        $guestCart->delete();
    }
}
