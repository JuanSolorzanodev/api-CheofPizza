<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRole
{
    /**
     * Restringe una ruta a uno o varios roles.
     *
     * Ejemplos:
     *
     * role:admin
     * role:operator,admin
     * role:customer,operator,admin
     *
     * @param string ...$roles Roles enviados por Laravel
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles,
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

        /*
         * Normalizamos los parámetros por seguridad.
         *
         * Laravel normalmente entrega:
         * ['operator', 'admin']
         *
         * También soportamos accidentalmente:
         * ['operator,admin']
         */
        $allowedRoles = collect($roles)
            ->flatMap(
                static fn (string $role): array =>
                    explode(',', $role),
            )
            ->map(
                static fn (string $role): string =>
                    strtolower(trim($role)),
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $userRole = strtolower(
            trim(
                (string) (
                    $user->role?->role_name ?? ''
                ),
            ),
        );

        if (
            $userRole === '' ||
            ! in_array(
                $userRole,
                $allowedRoles,
                true,
            )
        ) {
            return new JsonResponse([
                'success' => false,
                'message' =>
                    'No tienes permisos para acceder a este recurso.',
                'code' => 'FORBIDDEN',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
