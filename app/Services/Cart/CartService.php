<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\PersonalizationAction;
use App\Services\Builder\BuilderQuoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Services\Promotion\PublicPromotionService;
use Illuminate\Validation\ValidationException;
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

        // Siempre mantenemos session_id para consistencia con el frontend
        $sessionId = $sessionId ?: $this->newSessionId();

        // -------------------------
        // Usuario autenticado
        // -------------------------
        if ($userId) {
            // 1) Carrito activo del usuario
            $userCart = Cart::where('user_id', $userId)
                ->where('cart_status_id', $activeStatusId)
                ->latest('id')
                ->first();

            if (!$userCart) {
                $userCart = Cart::create([
                    'user_id' => $userId,
                    'cart_status_id' => $activeStatusId,
                    'session_id' => $sessionId, // <- ya no null
                    'total' => 0,
                ]);
            } else {
                // Asegura session_id para enviar X-Cart-Session siempre
                if (!$userCart->session_id) {
                    $userCart->session_id = $sessionId;
                    $userCart->save();
                }
            }

            // 2) Si existe carrito invitado con esta session, lo reclamamos/mergeamos
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

        // -------------------------
        // Invitado
        // -------------------------
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


    public function addPromotion(Cart $cart, array $payload): Cart
    {
        return $cart->getConnection()->transaction(function () use ($cart, $payload) {
            $cart = $this->loadCart($cart);

            $promotion = $this->promotionService->findActiveByIdOrFail((int) $payload['promotion_id']);

            $validatedSelection = $this->promotionService->validateSelectedPizzasForPromotion(
                $promotion,
                array_map('intval', $payload['selected_pizza_ids'] ?? [])
            );

            $quantity = (int) ($payload['quantity'] ?? 1);
            $sizeId = (int) $validatedSelection['size_id'];
            $selectedPizzaIds = $validatedSelection['selected_pizzas']
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->values()
                ->all();

            $unitPrice = (float) $promotion->promotion_price;
            $subTotal = round($unitPrice * $quantity, 2);

            $existing = $this->findEquivalentPromotionItemInLoadedCart(
                cart: $cart,
                promotionId: (int) $promotion->id,
                sizeId: $sizeId,
                selectedPizzaIds: $selectedPizzaIds,
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

            foreach ($validatedSelection['selected_pizzas'] as $pizza) {
                $item->cartPromotionItems()->create([
                    'pizza_id' => (int) $pizza->id,
                ]);
            }

            $this->recalculateCartTotal($cart);

            return $this->loadCart($cart);
        });
    }

    public function addPizza(Cart $cart, array $payload): Cart
    {
        // Transacción usando conexión del modelo (sin Facade DB)
        return $cart->getConnection()->transaction(function () use ($cart, $payload) {
            $cart = $this->loadCart($cart);

            // Precios en servidor
            $quote = $this->quoteService->quote($payload);

            $qty    = (int) ($payload['quantity'] ?? 1);
            $sizeId = (int) ($payload['size_id'] ?? 0);

            $isHalf   = (bool) ($payload['is_half_and_half'] ?? false);
            $pizzaAId = (int) ($payload['pizza_id'] ?? 0);
            $pizzaBId = $isHalf ? (int) ($payload['second_pizza_id'] ?? 0) : null;

            $unitPrice = (float) ($quote['unit_price'] ?? 0);
            $subTotal  = round($unitPrice * $qty, 2);

            $extras = (array) ($payload['extras'] ?? []);

            // Merge con item equivalente (comparando desde colección cargada)
            $existing = $this->findEquivalentPizzaItemInLoadedCart(
                cart: $cart,
                pizzaAId: $pizzaAId,
                pizzaBId: $pizzaBId,
                isHalf: $isHalf,
                sizeId: $sizeId,
                extras: $extras
            );

            if ($existing) {
                $existing->quantity   = (int) $existing->quantity + $qty;
                $existing->unit_price = $unitPrice;
                $existing->subtotal   = round(((float) $existing->quantity) * $unitPrice, 2);
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

            // Personalizaciones (extras)
            $extraActionId = $this->extraActionIdOrFail();

            $breakdown = collect($quote['extras_breakdown'] ?? [])
                ->keyBy(fn($x) => ((int) $x['ingredient_id']) . '|' . ((string) $x['applies_to']));

            foreach ($extras as $ex) {
                $ingredientId = (int) ($ex['ingredient_id'] ?? 0);
                $appliesTo    = (string) ($ex['applies_to'] ?? 'ALL');

                $key = $ingredientId . '|' . $appliesTo;
                $line = $breakdown->get($key);

                $extraPrice = (float) ($line['line_total'] ?? 0);

                $item->cartItemPersonalizations()->create([
                    'ingredient_id' => $ingredientId,
                    'personalization_action_id' => $extraActionId,
                    'applies_to' => $appliesTo,
                    'extra_price' => $extraPrice,
                ]);
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

            // Borrado ORM desde colección (delete por item)
            $cart->cartItems->each->delete();

            $cart->total = 0;
            $cart->save();

            return $this->loadCart($cart);
        });
    }

    // -----------------
    // Internals (ORM)
    // -----------------

    private function loadCart(Cart $cart): Cart
    {
        $cart->load([
            'cartStatus',
            'cartItems.pizza.category',
            'cartItems.pizzaSecond.category',
            'cartItems.promotion.promotionDetails.category',
            'cartItems.promotion.promotionDetails.size',
            'cartItems.size',
            'cartItems.cartPromotionItems.pizza.category',
            'cartItems.cartItemPersonalizations.ingredient',
            'cartItems.cartItemPersonalizations.personalizationAction',
        ]);

        return $cart->fresh([
            'cartStatus',
            'cartItems.pizza.category',
            'cartItems.pizzaSecond.category',
            'cartItems.promotion.promotionDetails.category',
            'cartItems.promotion.promotionDetails.size',
            'cartItems.size',
            'cartItems.cartPromotionItems.pizza.category',
            'cartItems.cartItemPersonalizations.ingredient',
            'cartItems.cartItemPersonalizations.personalizationAction',
        ]) ?? $cart;
    }

    private function recalculateCartTotal(Cart $cart): void
    {
        // Total por colección cargada (sin sum() de query builder)
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

    private function extraActionIdOrFail(): int
    {
        $action = PersonalizationAction::where('action_name', 'Extra')->first();

        if (!$action) {
            throw new RuntimeException("PersonalizationAction 'Extra' no existe. Revisa seeders.");
        }

        return (int) $action->id;
    }

    private function newSessionId(): string
    {
        return Str::uuid()->toString();
    }
    private function findEquivalentPromotionItemInLoadedCart(
        Cart $cart,
        int $promotionId,
        int $sizeId,
        array $selectedPizzaIds
    ): ?CartItem {
        $wanted = $this->normalizePizzaIds($selectedPizzaIds);

        $candidates = $cart->cartItems
            ->where('item_type', 'promotion')
            ->where('promotion_id', $promotionId)
            ->where('size_id', $sizeId)
            ->values();

        foreach ($candidates as $item) {
            $have = $item->cartPromotionItems
                ->pluck('pizza_id')
                ->map(fn($id) => (int) $id)
                ->sort()
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
        array $extras
    ): ?CartItem {
        $wanted = $this->normalizeExtras($extras);

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
            $candidates = $candidates->filter(fn(CartItem $i) => $i->pizza_id_second === null)->values();
        }

        foreach ($candidates as $item) {
            $have = $item->cartItemPersonalizations
                ->map(fn($p) => [
                    'ingredient_id' => (int) $p->ingredient_id,
                    'applies_to' => (string) $p->applies_to,
                ])
                ->sortBy([['ingredient_id', 'asc'], ['applies_to', 'asc']])
                ->values()
                ->all();

            if ($have === $wanted) {
                return $item;
            }
        }

        return null;
    }


    private function normalizePizzaIds(array $pizzaIds): array
    {
        return collect($pizzaIds)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->sort()
            ->values()
            ->all();
    }
    private function normalizeExtras(array $extras): array
    {
        return collect($extras)
            ->map(fn($e) => [
                'ingredient_id' => (int) ($e['ingredient_id'] ?? 0),
                'applies_to' => (string) ($e['applies_to'] ?? 'ALL'),
            ])
            ->filter(fn($e) => $e['ingredient_id'] > 0)
            ->sortBy([['ingredient_id', 'asc'], ['applies_to', 'asc']])
            ->values()
            ->all();
    }

    private function mergeGuestCartIntoUserCart(Cart $guestCart, Cart $userCart): void
    {
        // Cargamos ambos para comparar equivalencias y extras
        $guestCart = $this->loadCart($guestCart);
        $userCart  = $this->loadCart($userCart);

        foreach ($guestCart->cartItems as $guestItem) {
            if ($guestItem->item_type === 'promotion') {
                $selectedPizzaIds = $guestItem->cartPromotionItems
                    ->pluck('pizza_id')
                    ->map(fn($id) => (int) $id)
                    ->values()
                    ->all();

                $existingPromotion = $this->findEquivalentPromotionItemInLoadedCart(
                    cart: $userCart,
                    promotionId: (int) $guestItem->promotion_id,
                    sizeId: (int) $guestItem->size_id,
                    selectedPizzaIds: $selectedPizzaIds
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

            $extras = $guestItem->cartItemPersonalizations
                ->map(fn($p) => [
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
                extras: $extras
            );

            if ($existing) {
                // Merge cantidad
                $existing->quantity = (int) $existing->quantity + (int) $guestItem->quantity;
                // Mantén el unit_price del guest si quieres priorizar lo más reciente
                $existing->unit_price = (float) $guestItem->unit_price;
                $existing->subtotal = round(((float) $existing->quantity) * (float) $existing->unit_price, 2);
                $existing->save();

                // El invitado ya se integró, borramos el item (y sus personalizaciones por FK/cascade si aplica)
                $guestItem->delete();

                // Importante: refrescar el userCart cargado para que equivalencias futuras consideren el merge
                $userCart = $this->loadCart($userCart);
            } else {
                // No hay equivalente: movemos el item con sus personalizaciones intactas
                $guestItem->cart_id = $userCart->id;
                $guestItem->save();
                $userCart = $this->loadCart($userCart);
            }
        }

        // Recalcular total del carrito final
        $this->recalculateCartTotal($userCart);

        // El carrito invitado queda vacío o ya no sirve: lo eliminamos
        $guestCart->delete();
    }
}
