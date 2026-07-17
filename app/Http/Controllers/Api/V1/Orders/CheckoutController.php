<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Requests\Api\V1\Orders\CheckoutRequest;
use App\Http\Resources\Api\V1\BankAccountPublicResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Services\Cart\CartService;
use App\Services\Order\CheckoutService;
use App\Services\Payments\TransferAccountService;
use Illuminate\Http\JsonResponse;

final class CheckoutController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly TransferAccountService $transferAccountService,
    ) {}

    /**
     * Devuelve la configuración pública requerida por el checkout.
     *
     * GET /api/v1/public/checkout/config
     */
    public function config(): JsonResponse
    {
        $transferAccount = $this->transferAccountService->getActivePrimary();

        $paypalClientId = (string) config('paypal.client_id', '');

        return response()->json([
            'data' => [
                'transfer' => $transferAccount !== null
                    ? new BankAccountPublicResource($transferAccount)
                    : null,

                'paypal' => [
                    'enabled' => $paypalClientId !== '',
                    'client_id' => $paypalClientId,
                    'currency' => (string) config(
                        'paypal.currency',
                        'USD'
                    ),
                    'locale' => (string) config(
                        'paypal.locale',
                        'es-EC'
                    ),
                ],
            ],
        ]);
    }

    /**
     * Crea un pedido mediante efectivo o transferencia.
     *
     * Los pagos con tarjeta se procesan mediante el flujo de PayPal.
     *
     * POST /api/v1/checkout
     */
    public function checkout(
        CheckoutRequest $request
    ): OrderResource {
        $sessionId = $request->header('X-Cart-Session');

        $userId = (int) $request
            ->user()
            ->getAuthIdentifier();

        $cart = $this->cartService->getOrCreateActiveCart(
            userId: $userId,
            sessionId: $sessionId,
        );

        $order = $this->checkoutService->checkout(
            cart: $cart,
            payload: $request->validated(),
        );

        return new OrderResource($order);
    }
}
