<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MachineLearning\MachineLearningClientContract;
use App\Services\MachineLearning\MachineLearningClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra las dependencias de infraestructura.
     */
    public function register(): void
    {
        $this->app->singleton(
            MachineLearningClientContract::class,
            MachineLearningClient::class,
        );
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Inicio de sesión con correo y contraseña
        |--------------------------------------------------------------------------
        |
        | El límite se aplica por combinación de dirección IP y correo.
        | De esta forma, los intentos realizados sobre un correo no afectan
        | automáticamente a otros usuarios que comparten la misma conexión.
        |
        */

        RateLimiter::for(
            'auth-login',
            fn (Request $request): Limit => Limit::perMinute(10)
                ->by(
                    $request->ip()
                    .'|'
                    .strtolower(
                        trim(
                            (string) $request->input(
                                'email',
                                '',
                            ),
                        ),
                    ),
                )
                ->response(
                    fn () => response()->json(
                        [
                            'success' => false,
                            'message' => (
                                'Demasiados intentos de inicio de sesión. '
                                .'Intenta nuevamente en un momento.'
                            ),
                            'code' => 'TOO_MANY_LOGIN_ATTEMPTS',
                        ],
                        429,
                    ),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Registro de clientes
        |--------------------------------------------------------------------------
        |
        | El límite se aplica por dirección IP, ya que una misma dirección
        | no debería crear una cantidad excesiva de cuentas por minuto.
        |
        */

        RateLimiter::for(
            'auth-register',
            fn (Request $request): Limit => Limit::perMinute(5)
                ->by(
                    $request->ip(),
                )
                ->response(
                    fn () => response()->json(
                        [
                            'success' => false,
                            'message' => (
                                'Has realizado demasiados intentos de registro. '
                                .'Intenta nuevamente en un momento.'
                            ),
                            'code' => 'TOO_MANY_REGISTRATION_ATTEMPTS',
                        ],
                        429,
                    ),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Autenticación mediante Google y Firebase
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'auth-google',
            fn (Request $request): Limit => Limit::perMinute(10)
                ->by(
                    $request->ip(),
                )
                ->response(
                    fn () => response()->json(
                        [
                            'success' => false,
                            'message' => (
                                'Demasiados intentos de acceso con Google. '
                                .'Intenta nuevamente en un momento.'
                            ),
                            'code' => 'TOO_MANY_GOOGLE_AUTH_ATTEMPTS',
                        ],
                        429,
                    ),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | API pública
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'public-api',
            fn (Request $request): Limit => Limit::perMinute(120)
                ->by(
                    $request->ip(),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Carrito
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'cart',
            fn (Request $request): Limit => Limit::perMinute(90)
                ->by(
                    $this->requestIdentity(
                        $request,
                    ),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Geolocalización
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'geo',
            fn (Request $request): Limit => Limit::perMinute(30)
                ->by(
                    $this->requestIdentity(
                        $request,
                    ),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Checkout
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'checkout',
            fn (Request $request): Limit => Limit::perMinute(10)
                ->by(
                    $this->requestIdentity(
                        $request,
                    ),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Pagos
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'payments',
            fn (Request $request): Limit => Limit::perMinute(20)
                ->by(
                    $this->requestIdentity(
                        $request,
                    ),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Webhook de PayPal
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'paypal-webhook',
            fn (Request $request): Limit => Limit::perMinute(180)
                ->by(
                    $request->ip(),
                ),
        );

        /*
        |--------------------------------------------------------------------------
        | Acciones de operadores y administradores
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'operator-actions',
            fn (Request $request): Limit => Limit::perMinute(120)
                ->by(
                    $this->requestIdentity(
                        $request,
                    ),
                ),
        );
    }

    /**
     * Obtiene una identidad estable para aplicar límites de solicitudes.
     *
     * Prioridad:
     * 1. Usuario autenticado.
     * 2. Sesión del carrito.
     * 3. Dirección IP.
     */
    private function requestIdentity(
        Request $request,
    ): string {
        $userId = $request
            ->user()
            ?->getAuthIdentifier();

        if ($userId !== null) {
            return 'user:'.$userId;
        }

        $cartSession = trim(
            (string) $request->header(
                'X-Cart-Session',
                '',
            ),
        );

        if ($cartSession !== '') {
            return 'cart:'.$cartSession;
        }

        return 'ip:'.$request->ip();
    }
}
