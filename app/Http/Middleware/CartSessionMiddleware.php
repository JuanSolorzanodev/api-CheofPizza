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
        $cartSession = $request->header('X-Cart-Session');

        if (empty($cartSession)) {
            $cartSession = (string) Str::uuid();
        }

        $request->attributes->set('cart_session', $cartSession);

        $response = $next($request);

        $response->headers->set(
            'X-Cart-Session',
            $request->attributes->get('cart_session')
        );

        return $response;
    }
}
