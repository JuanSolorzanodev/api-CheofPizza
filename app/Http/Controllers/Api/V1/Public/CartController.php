<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Requests\Api\V1\Public\CartAddPizzaRequest;
use App\Http\Requests\Api\V1\Public\CartAddPromotionRequest;
use App\Http\Requests\Api\V1\Public\CartUpdateQuantityRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Cart;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CartController
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function show(
        Request $request,
    ): Response {
        return $this->cartResponse(
            $this->resolveCart($request),
        );
    }

    public function addPizza(
        CartAddPizzaRequest $request,
    ): Response {
        $cart = $this->cartService->addPizza(
            cart: $this->resolveCart($request),
            payload: $request->validated(),
        );

        return $this->cartResponse(
            $cart,
        );
    }

    public function addPromotion(
        CartAddPromotionRequest $request,
    ): Response {
        $cart = $this->cartService->addPromotion(
            cart: $this->resolveCart($request),
            payload: $request->validated(),
        );

        return $this->cartResponse(
            $cart,
        );
    }

    public function updateQuantity(
        CartUpdateQuantityRequest $request,
        int $itemId,
    ): Response {
        $cart = $this->cartService->updateQuantity(
            cart: $this->resolveCart($request),
            cartItemId: $itemId,
            quantity: (int) $request->validated(
                'quantity',
            ),
        );

        return $this->cartResponse(
            $cart,
        );
    }

    public function remove(
        Request $request,
        int $itemId,
    ): Response {
        $cart = $this->cartService->removeItem(
            cart: $this->resolveCart($request),
            cartItemId: $itemId,
        );

        return $this->cartResponse(
            $cart,
        );
    }

    public function clear(
        Request $request,
    ): Response {
        $cart = $this->cartService->clear(
            $this->resolveCart($request),
        );

        return $this->cartResponse(
            $cart,
        );
    }

    private function resolveCart(
        Request $request,
    ): Cart {
        $sessionId = $request->header(
            'X-Cart-Session',
        );

        $userId = $request->user()?->id;

        return $this->cartService
            ->getOrCreateActiveCart(
                userId: $userId !== null
                    ? (int) $userId
                    : null,

                sessionId: is_string($sessionId)
                    ? $sessionId
                    : null,
            );
    }

    private function cartResponse(
        Cart $cart,
    ): Response {
        $cart = $cart->relationLoaded('cartItems')
            ? $cart
            : $this->cartService->loadForResponse(
                $cart,
            );

        return (new CartResource($cart))
            ->response()
            ->header(
                'X-Cart-Session',
                (string) $cart->session_id,
            );
    }
}
