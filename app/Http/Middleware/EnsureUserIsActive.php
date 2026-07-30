<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse([
                'success' => false,
                'message' =>
                    'Debes iniciar sesión para acceder a este recurso.',
                'code' => 'UNAUTHENTICATED',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active) {
            /*
             * Revocamos el token usado en esta solicitud.
             */
            $user->currentAccessToken()?->delete();

            return new JsonResponse([
                'success' => false,
                'message' =>
                    'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',
                'code' => 'USER_INACTIVE',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
