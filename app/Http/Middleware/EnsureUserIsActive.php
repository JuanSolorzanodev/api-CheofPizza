<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ((bool) $user->is_active) {
            return $next($request);
        }

        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        return ApiResponse::error(
            message: 'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',
            status: Response::HTTP_FORBIDDEN,
            code: 'USER_INACTIVE',
        );
    }
}
