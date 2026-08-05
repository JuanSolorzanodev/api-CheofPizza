<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AddRequestId
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $requestId = $this->resolveRequestId($request);

        /*
         * El identificador queda disponible durante toda la petición.
         */
        $request->attributes->set(
            'request_id',
            $requestId,
        );

        /*
         * Laravel agregará estos datos a los logs generados
         * durante esta petición.
         */
        Log::withContext([
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(
            'X-Request-Id',
            $requestId,
        );

        return $response;
    }

    private function resolveRequestId(
        Request $request,
    ): string {
        $providedRequestId = trim(
            (string) $request->header(
                'X-Request-Id',
                '',
            ),
        );

        /*
         * Aceptamos identificadores externos razonables, por ejemplo
         * los enviados por Railway, Cloudflare o el frontend.
         */
        if (
            $providedRequestId !== ''
            && mb_strlen($providedRequestId) <= 100
            && preg_match(
                '/^[A-Za-z0-9._:-]+$/',
                $providedRequestId,
            ) === 1
        ) {
            return $providedRequestId;
        }

        return (string) Str::uuid();
    }
}
