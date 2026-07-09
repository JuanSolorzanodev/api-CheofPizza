<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Requests\Api\V1\Public\CartAddPizzaRequest;
use App\Http\Requests\Api\V1\Public\CartAddPromotionRequest;
use App\Http\Requests\Api\V1\Public\CartUpdateQuantityRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;

class CartController
{
    public function __construct(private readonly CartService $cartService) {}

    public function show(Request $request)
    {
        $cart = $this->resolveCart($request);

        return (new CartResource($cart))
            ->response()
            ->header('X-Cart-Session', $cart->session_id);
    }

    public function addPizza(CartAddPizzaRequest $request)
    {
        $cart = $this->resolveCart($request);

        $cart = $this->cartService->addPizza($cart, $request->validated());

        return (new CartResource($cart))
            ->response()
            ->header('X-Cart-Session', $cart->session_id);
    }

    public function addPromotion(CartAddPromotionRequest $request)
    {
        $cart = $this->resolveCart($request);

        $cart = $this->cartService->addPromotion($cart, $request->validated());

        return (new CartResource($cart))
            ->response()
            ->header('X-Cart-Session', $cart->session_id);
    }

    public function updateQuantity(CartUpdateQuantityRequest $request, int $itemId)
    {
        $cart = $this->resolveCart($request);

        $cart = $this->cartService->updateQuantity(
            $cart,
            $itemId,
            (int) $request->validated('quantity')
        );

        return (new CartResource($cart))
            ->response()
            ->header('X-Cart-Session', $cart->session_id);
    }

    public function remove(Request $request, int $itemId)
    {
        $cart = $this->resolveCart($request);

        $cart = $this->cartService->removeItem($cart, $itemId);

        return (new CartResource($cart))
            ->response()
            ->header('X-Cart-Session', $cart->session_id);
    }

    public function clear(Request $request)
    {
        $cart = $this->resolveCart($request);

        $cart = $this->cartService->clear($cart);

        return (new CartResource($cart))
            ->response()
            ->header('X-Cart-Session', $cart->session_id);
    }

    private function resolveCart(Request $request)
    {
        $sessionId = $request->attributes->get('cart_session');//$request->header('X-Cart-Session');
        $userId = $request->user()?->id;

        $cart = $this->cartService->getOrCreateActiveCart($userId, $sessionId);

        return $cart->load([
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
    }
}
