<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\FirebaseGoogleLoginRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ApiResponse $response
    ) {}

    public function loginWithGoogle(FirebaseGoogleLoginRequest $request): JsonResponse
    {

        $login = $this->authService->loginWithGoogle(
            data: $request->validated(),
            sessionId: $request->attributes->get('cart_session')
        );

        return $this->response
            ->success(
                data: [
                    'token' => $login->token,
                    'user' => new UserResource($login->user),
                    'cart' => new CartResource($login->cart),
                ],
                message: 'Login successful.'
            )
            ->header(
                'X-Cart-Session',
                $login->cartSession
            );
    }
}
