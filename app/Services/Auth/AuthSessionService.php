<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Http\Resources\Api\V1\AuthUserResource;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;

final class AuthSessionService
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function createResponse(
        User $user,
        string $tokenName,
        ?string $cartSessionId,
        string $message = 'Sesión iniciada correctamente.',
        int $status = 200,
    ): JsonResponse {
        $plainTextToken = $user
            ->createToken(
                $tokenName,
            )
            ->plainTextToken;

        $cart = $this->cartService
            ->getOrCreateActiveCart(
                userId: (int) $user->id,

                sessionId: $cartSessionId,
            );

        $user->loadMissing(
            'role',
        );

        return response()
            ->json([
                'success' => true,

                'message' => $message,

                'data' => [
                    'token' => $plainTextToken,

                    'user' => new AuthUserResource(
                        $user,
                    ),

                    'cart' => new CartResource(
                        $cart,
                    ),
                ],
            ], $status)
            ->header(
                'X-Cart-Session',
                (string) $cart->session_id,
            );
    }

    public function resolveCartSessionId(
        ?string $headerSessionId,
        ?string $bodySessionId,
    ): ?string {
        $sessionId = trim(
            (string) (
                $headerSessionId
                ?: $bodySessionId
            ),
        );

        return $sessionId !== ''
            ? $sessionId
            : null;
    }
}
