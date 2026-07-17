<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken !== null) {
            $accessToken->delete();
        }

        return ApiResponse::success(
            data: null,
            message: 'Sesión cerrada correctamente.',
        );
    }
}
