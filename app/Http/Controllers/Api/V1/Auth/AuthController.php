<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\FirebaseGoogleLoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Support\Str;
use Throwable;

class AuthController
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function loginWithGoogle(FirebaseGoogleLoginRequest $request): JsonResponse
    {
        $result = $this->authService->loginWithGoogle(
            $request->validated(),
            $request->header('X-Cart-Session')
        );

        return response()
            ->json($result['data'])
            ->header(
                'X-Cart-Session',
                $result['cart_session']
            );
    }
}
