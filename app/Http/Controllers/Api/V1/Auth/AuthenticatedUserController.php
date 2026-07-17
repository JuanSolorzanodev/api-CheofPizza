<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\Api\V1\AuthUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthenticatedUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error(
                message: 'La sesión no es válida.',
                status: 401,
                code: 'UNAUTHENTICATED',
            );
        }

        $user->loadMissing('role');

        return ApiResponse::success(
            data: new AuthUserResource($user),
            message: 'Sesión recuperada correctamente.',
        );
    }
}
