<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for(
            'auth',
            fn (Request $request): Limit => Limit::perMinute(10)
                ->by(
                    $request->ip().'|'.strtolower(
                        (string) $request->input('email', 'firebase')
                    )
                )
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Demasiados intentos de inicio de sesión. Intenta nuevamente en un momento.',
                    'code' => 'TOO_MANY_AUTH_ATTEMPTS',
                ], 429)),
        );

        RateLimiter::for(
            'public-api',
            fn (Request $request): Limit => Limit::perMinute(120)
                ->by($request->ip()),
        );

        RateLimiter::for(
            'cart',
            fn (Request $request): Limit => Limit::perMinute(90)
                ->by($this->requestIdentity($request)),
        );

        RateLimiter::for(
            'geo',
            fn (Request $request): Limit => Limit::perMinute(30)
                ->by($this->requestIdentity($request)),
        );

        RateLimiter::for(
            'checkout',
            fn (Request $request): Limit => Limit::perMinute(10)
                ->by($this->requestIdentity($request)),
        );

        RateLimiter::for(
            'payments',
            fn (Request $request): Limit => Limit::perMinute(20)
                ->by($this->requestIdentity($request)),
        );

        RateLimiter::for(
            'paypal-webhook',
            fn (Request $request): Limit => Limit::perMinute(180)
                ->by($request->ip()),
        );

        RateLimiter::for(
            'operator-actions',
            fn (Request $request): Limit => Limit::perMinute(120)
                ->by($this->requestIdentity($request)),
        );
    }

    private function requestIdentity(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            return 'user:'.$userId;
        }

        $cartSession = trim(
            (string) $request->header('X-Cart-Session', '')
        );

        if ($cartSession !== '') {
            return 'cart:'.$cartSession;
        }

        return 'ip:'.$request->ip();
    }
}
