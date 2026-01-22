<?php

namespace App\Http\Controllers\Api\V1\Orders;

use Illuminate\Http\JsonResponse;

use App\Http\Requests\Api\V1\Orders\CheckoutRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\BankAccountPublicResource;

use App\Services\Cart\CartService;
use App\Services\Order\CheckoutService;
use App\Services\Payments\TransferAccountService;

class CheckoutController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly TransferAccountService $transferAccountService,
    ) {}

    /**
     * PUBLIC: Configuración de checkout (por ahora solo transferencia).
     * GET /api/v1/public/checkout/config
     */
    public function config(): JsonResponse
    {
        $account = $this->transferAccountService->getActivePrimary();

        return response()->json([
            'data' => [
                'transfer' => $account ? new BankAccountPublicResource($account) : null,
            ],
        ]);
    }

    /**
     * AUTH: Confirmar checkout y crear orden.
     * POST /api/v1/checkout
     */
    public function checkout(CheckoutRequest $request)
    {
        $sessionId = $request->header('X-Cart-Session');
        $userId = $request->user()->id;

        // Esto también hace merge del carrito invitado si existe con esa sesión.
        $cart = $this->cartService->getOrCreateActiveCart($userId, $sessionId);

        $order = $this->checkoutService->checkout($cart, $request->validated());

        return new OrderResource($order);
    }
}
