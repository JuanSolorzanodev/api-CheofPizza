<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\OptionalSanctumAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => [
                'api',
                'auth:sanctum',
            ],
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);

        /*
         * Railway, Cloudflare y otros proveedores terminan HTTPS
         * delante de Laravel.
         */
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->alias([
            'auth.optional' => OptionalSanctumAuth::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
    static fn (
        Request $request,
        \Throwable $exception
    ): bool => $request->is('api/*')
        || $request->expectsJson(),
);

$exceptions->render(
    static function (
        \Throwable $exception,
        Request $request
    ) {
        if (
            !$request->is('api/*')
            && !$request->expectsJson()
        ) {
            return null;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return null;
        }

        if (app()->isProduction()) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error interno en el servidor.',
                'code' => 'INTERNAL_SERVER_ERROR',
            ], 500);
        }

        return null;
    }
);
    })
    ->create();
