<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddSecurityHeaders
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        /** @var Response $response */
        $response = $next($request);

        /*
         * Evita que los navegadores intenten interpretar una respuesta
         * con un tipo MIME diferente al declarado por la API.
         */
        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff',
        );

        /*
         * La API no debe ser cargada dentro de iframes.
         */
        $response->headers->set(
            'X-Frame-Options',
            'DENY',
        );

        /*
         * Evita compartir información de navegación mediante Referer.
         */
        $response->headers->set(
            'Referrer-Policy',
            'no-referrer',
        );

        /*
         * La API no requiere acceso directo a capacidades del dispositivo.
         */
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()',
        );

        /*
         * Se añade HSTS únicamente cuando Laravel reconoce una conexión
         * HTTPS. En Railway esto funciona junto con trustProxies().
         */
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
