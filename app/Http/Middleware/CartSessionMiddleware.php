<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CartSessionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->header('X-Cart-Session');

        if (empty($sessionId)) {
            $sessionId = Str::uuid()->toString();
        }

        // Guardamos el valor dentro del Request
        $request->attributes->set(
            'cart_session',
            $sessionId
        );

        return $next($request);
    }
}
