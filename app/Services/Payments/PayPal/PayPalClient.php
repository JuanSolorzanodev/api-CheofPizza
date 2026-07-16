<?php

declare(strict_types=1);

namespace App\Services\Payments\PayPal;

use App\Exceptions\Payments\PayPalApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class PayPalClient
{
    private const ACCESS_TOKEN_CACHE_KEY =
        'payments.paypal.access_token';

    public function baseUrl(): string
    {
        $mode = (string) config(
            'paypal.mode',
            'sandbox'
        );

        $baseUrl = config(
            "paypal.base_urls.{$mode}"
        );

        if (
            ! is_string($baseUrl)
            || $baseUrl === ''
        ) {
            throw new RuntimeException(
                "La URL de PayPal no está configurada para [{$mode}]."
            );
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function getAccessTokenResponse(): array
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withBasicAuth(
                    $this->clientId(),
                    $this->clientSecret(),
                )
                ->connectTimeout(
                    $this->connectTimeout()
                )
                ->timeout(
                    $this->timeout()
                )
                ->post(
                    $this->baseUrl()
                        .'/v1/oauth2/token',
                    [
                        'grant_type' =>
                            'client_credentials',
                    ]
                );
        } catch (ConnectionException $exception) {
            throw new PayPalApiException(
                message:
                    'No fue posible conectar con PayPal.',
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw $this->createApiException(
                $response,
                'PayPal rechazó la autenticación OAuth.'
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }

    public function accessToken(): string
    {
        $cachedToken = Cache::get(
            self::ACCESS_TOKEN_CACHE_KEY
        );

        if (
            is_string($cachedToken)
            && $cachedToken !== ''
        ) {
            return $cachedToken;
        }

        $payload = $this->getAccessTokenResponse();

        $accessToken =
            $payload['access_token'] ?? null;

        $expiresIn =
            $payload['expires_in'] ?? null;

        if (
            ! is_string($accessToken)
            || $accessToken === ''
        ) {
            throw new PayPalApiException(
                'PayPal no devolvió un access token válido.'
            );
        }

        $ttl = is_numeric($expiresIn)
            ? max(((int) $expiresIn) - 60, 60)
            : 300;

        Cache::put(
            self::ACCESS_TOKEN_CACHE_KEY,
            $accessToken,
            now()->addSeconds($ttl),
        );

        return $accessToken;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function post(
        string $uri,
        array $payload,
        string $requestId,
    ): array {
        try {
            $response = $this
                ->authenticatedRequest()
                ->withHeaders([
                    'PayPal-Request-Id' =>
                        $requestId,
                    'Prefer' =>
                        'return=representation',
                ])
                ->post(
                    $this->url($uri),
                    $payload,
                );
        } catch (ConnectionException $exception) {
            throw new PayPalApiException(
                message:
                    'No fue posible conectar con PayPal.',
                previous: $exception,
            );
        }

        if ($response->failed()) {
            /*
             * El token pudo expirar o ser invalidado.
             * Se limpia para que una llamada posterior
             * solicite uno nuevo.
             */
            if ($response->status() === 401) {
                $this->forgetAccessToken();
            }

            throw $this->createApiException(
                $response,
                'PayPal rechazó la operación.'
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $uri): array
    {
        try {
            $response = $this
                ->authenticatedRequest()
                ->get(
                    $this->url($uri)
                );
        } catch (ConnectionException $exception) {
            throw new PayPalApiException(
                message:
                    'No fue posible conectar con PayPal.',
                previous: $exception,
            );
        }

        if ($response->failed()) {
            if ($response->status() === 401) {
                $this->forgetAccessToken();
            }

            throw $this->createApiException(
                $response,
                'No fue posible consultar PayPal.'
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    public function authenticatedRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken(
                $this->accessToken()
            )
            ->connectTimeout(
                $this->connectTimeout()
            )
            ->timeout(
                $this->timeout()
            );
    }

    public function forgetAccessToken(): void
    {
        Cache::forget(
            self::ACCESS_TOKEN_CACHE_KEY
        );
    }

    private function url(string $uri): string
    {
        return $this->baseUrl()
            .'/'
            .ltrim($uri, '/');
    }

    private function clientId(): string
    {
        $clientId = config(
            'paypal.client_id'
        );

        if (
            ! is_string($clientId)
            || $clientId === ''
        ) {
            throw new RuntimeException(
                'PAYPAL_CLIENT_ID no está configurado.'
            );
        }

        return $clientId;
    }

    private function clientSecret(): string
    {
        $clientSecret = config(
            'paypal.client_secret'
        );

        if (
            ! is_string($clientSecret)
            || $clientSecret === ''
        ) {
            throw new RuntimeException(
                'PAYPAL_CLIENT_SECRET no está configurado.'
            );
        }

        return $clientSecret;
    }

    private function timeout(): int
    {
        return max(
            (int) config(
                'paypal.timeout',
                20
            ),
            1
        );
    }

    private function connectTimeout(): int
    {
        return max(
            (int) config(
                'paypal.connect_timeout',
                10
            ),
            1
        );
    }

    private function createApiException(
        Response $response,
        string $fallbackMessage,
    ): PayPalApiException {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $response->json();
        } catch (Throwable) {
            $payload = [];
        }

        $debugId = $response->header(
            'PayPal-Debug-Id'
        );

        if (
            ! is_string($debugId)
            || $debugId === ''
        ) {
            $debugId = isset($payload['debug_id'])
                && is_string($payload['debug_id'])
                    ? $payload['debug_id']
                    : null;
        }

        $name = isset($payload['name'])
            && is_string($payload['name'])
                ? $payload['name']
                : null;

        $message = isset($payload['message'])
            && is_string($payload['message'])
                ? $payload['message']
                : $fallbackMessage;

        $details = isset($payload['details'])
            && is_array($payload['details'])
                ? $payload['details']
                : null;

        return new PayPalApiException(
            message: $message,
            statusCode: $response->status(),
            debugId: $debugId,
            paypalErrorName: $name,
            details: $details,
        );
    }

    /**
 * Ejecuta un POST que requiere un objeto JSON vacío.
 *
 * PayPal espera {} en determinados endpoints, como la captura
 * de una orden. Un array PHP vacío se serializaría como [],
 * lo cual no cumple el esquema esperado.
 *
 * @return array<string, mixed>
 */
public function postEmptyObject(
    string $uri,
    string $requestId,
): array {
    try {
        $response = $this
            ->authenticatedRequest()
            ->withHeaders([
                'PayPal-Request-Id' => $requestId,
                'Prefer' => 'return=representation',
            ])
            ->withBody(
                '{}',
                'application/json'
            )
            ->post(
                $this->url($uri)
            );
    } catch (ConnectionException $exception) {
        throw new PayPalApiException(
            message: 'No fue posible conectar con PayPal.',
            previous: $exception,
        );
    }

    if ($response->failed()) {
        if ($response->status() === 401) {
            $this->forgetAccessToken();
        }

        throw $this->createApiException(
            response: $response,
            fallbackMessage:
                'PayPal rechazó la operación.',
        );
    }

    /** @var array<string, mixed> $data */
    $data = $response->json();

    return $data;
}
}
