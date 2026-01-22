<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticación opcional:
 * - Si llega Bearer token => intenta autenticar con Sanctum.
 * - Si no llega token => deja pasar como invitado.
 */
class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next, string $guard = 'sanctum')
    {
        if ($request->bearerToken()) {
            Auth::shouldUse($guard);
            Auth::guard($guard)->user(); // fuerza resolución del user
        }

        return $next($request);
    }
}
