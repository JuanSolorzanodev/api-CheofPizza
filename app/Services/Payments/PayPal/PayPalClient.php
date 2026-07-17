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
        $mode = trim(
            (string) config(
                'paypal.mode',
                'sandbox'
            )
        );

        $baseUrl = config(
            "paypal.base_urls.{$mode}"
        );

        if (
            !is_string($baseUrl)
            || trim($baseUrl) === ''
        ) {
            throw new RuntimeException(
                "La URL de PayPal no está configurada para el modo [{$mode}]."
            );
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * Solicita un access token OAuth a PayPal.
     *
     * @return array<string, mixed>
     *
     * @throws PayPalApiException
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
                        . '/v1/oauth2/token',
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
                response: $response,
                fallbackMessage:
                    'PayPal rechazó la autenticación OAuth.',
            );
        }

        $payload = $response->json();

        return is_array($payload)
            ? $payload
            : [];
    }

    /**
     * Obtiene el access token desde caché o solicita uno nuevo.
     *
     * @throws PayPalApiException
     */
    public function accessToken(): string
    {
        $cachedToken = Cache::get(
            self::ACCESS_TOKEN_CACHE_KEY
        );

        if (
            is_string($cachedToken)
            && trim($cachedToken) !== ''
        ) {
            return $cachedToken;
        }

        $payload = $this->getAccessTokenResponse();

        $accessToken =
            $payload['access_token'] ?? null;

        $expiresIn =
            $payload['expires_in'] ?? null;

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            throw new PayPalApiException(
                'PayPal no devolvió un access token válido.'
            );
        }

        $ttl = is_numeric($expiresIn)
            ? max(
                ((int) $expiresIn) - 60,
                60
            )
            : 300;

        Cache::put(
            self::ACCESS_TOKEN_CACHE_KEY,
            $accessToken,
            now()->addSeconds($ttl),
        );

        return $accessToken;
    }

    /**
     * Ejecuta una petición POST con payload JSON.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws PayPalApiException
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

        return $this->resolveResponse(
            response: $response,
            fallbackMessage:
                'PayPal rechazó la operación.',
        );
    }

    /**
     * Ejecuta un POST que requiere exactamente un objeto JSON vacío.
     *
     * PayPal espera `{}` en determinados endpoints, como la captura
     * de una orden. Un array PHP vacío podría serializarse como `[]`.
     *
     * @return array<string, mixed>
     *
     * @throws PayPalApiException
     */
    public function postEmptyObject(
        string $uri,
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
                ->withBody(
                    '{}',
                    'application/json'
                )
                ->post(
                    $this->url($uri)
                );
        } catch (ConnectionException $exception) {
            throw new PayPalApiException(
                message:
                    'No fue posible conectar con PayPal.',
                previous: $exception,
            );
        }

        return $this->resolveResponse(
            response: $response,
            fallbackMessage:
                'PayPal rechazó la operación.',
        );
    }

    /**
     * Ejecuta una petición GET autenticada.
     *
     * @return array<string, mixed>
     *
     * @throws PayPalApiException
     */
    public function get(
        string $uri
    ): array {
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

        return $this->resolveResponse(
            response: $response,
            fallbackMessage:
                'No fue posible consultar PayPal.',
        );
    }

    /**
     * Verifica la firma de un webhook usando PayPal.
     *
     * No debemos confiar en el contenido del webhook antes de que
     * PayPal confirme que su firma es válida.
     *
     * @param array{
     *     auth_algo: ?string,
     *     cert_url: ?string,
     *     transmission_id: ?string,
     *     transmission_sig: ?string,
     *     transmission_time: ?string
     * } $headers
     *
     * @param array<string, mixed> $webhookEvent
     *
     * @throws PayPalApiException
     * @throws RuntimeException
     */
    public function verifyWebhookSignature(
        array $headers,
        array $webhookEvent,
    ): bool {
        $webhookId = trim(
            (string) config(
                'paypal.webhook_id',
                ''
            )
        );

        if ($webhookId === '') {
            throw new RuntimeException(
                'PAYPAL_WEBHOOK_ID no está configurado.'
            );
        }

        $authAlgo = $this->requiredHeader(
            headers: $headers,
            key: 'auth_algo',
        );

        $certUrl = $this->requiredHeader(
            headers: $headers,
            key: 'cert_url',
        );

        $transmissionId = $this->requiredHeader(
            headers: $headers,
            key: 'transmission_id',
        );

        $transmissionSignature =
            $this->requiredHeader(
                headers: $headers,
                key: 'transmission_sig',
            );

        $transmissionTime =
            $this->requiredHeader(
                headers: $headers,
                key: 'transmission_time',
            );

        $payload = [
            'auth_algo' =>
                $authAlgo,

            'cert_url' =>
                $certUrl,

            'transmission_id' =>
                $transmissionId,

            'transmission_sig' =>
                $transmissionSignature,

            'transmission_time' =>
                $transmissionTime,

            'webhook_id' =>
                $webhookId,

            'webhook_event' =>
                $webhookEvent,
        ];

        try {
            $response = $this
                ->authenticatedRequest()
                ->post(
                    $this->url(
                        '/v1/notifications/verify-webhook-signature'
                    ),
                    $payload,
                );
        } catch (ConnectionException $exception) {
            throw new PayPalApiException(
                message:
                    'No fue posible verificar el webhook con PayPal.',
                previous: $exception,
            );
        }

        $data = $this->resolveResponse(
            response: $response,
            fallbackMessage:
                'PayPal rechazó la verificación del webhook.',
        );

        $verificationStatus = strtoupper(
            trim(
                (string) (
                    $data['verification_status']
                    ?? ''
                )
            )
        );

        return $verificationStatus === 'SUCCESS';
    }

    /**
     * Crea una petición autenticada estándar.
     */
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

    private function url(
        string $uri
    ): string {
        return $this->baseUrl()
            . '/'
            . ltrim($uri, '/');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PayPalApiException
     */
    private function resolveResponse(
        Response $response,
        string $fallbackMessage,
    ): array {
        if ($response->failed()) {
            if ($response->status() === 401) {
                $this->forgetAccessToken();
            }

            throw $this->createApiException(
                response: $response,
                fallbackMessage:
                    $fallbackMessage,
            );
        }

        $data = $response->json();

        return is_array($data)
            ? $data
            : [];
    }

    private function clientId(): string
    {
        $clientId = config(
            'paypal.client_id'
        );

        if (
            !is_string($clientId)
            || trim($clientId) === ''
        ) {
            throw new RuntimeException(
                'PAYPAL_CLIENT_ID no está configurado.'
            );
        }

        return trim($clientId);
    }

    private function clientSecret(): string
    {
        $clientSecret = config(
            'paypal.client_secret'
        );

        if (
            !is_string($clientSecret)
            || trim($clientSecret) === ''
        ) {
            throw new RuntimeException(
                'PAYPAL_CLIENT_SECRET no está configurado.'
            );
        }

        return trim($clientSecret);
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

    /**
     * @param array<string, mixed> $headers
     */
    private function requiredHeader(
        array $headers,
        string $key,
    ): string {
        $value = $headers[$key] ?? null;

        if (
            !is_string($value)
            || trim($value) === ''
        ) {
            throw new RuntimeException(
                "Falta el encabezado requerido del webhook: {$key}."
            );
        }

        return trim($value);
    }

    private function createApiException(
        Response $response,
        string $fallbackMessage,
    ): PayPalApiException {
        try {
            $decoded = $response->json();

            $payload = is_array($decoded)
                ? $decoded
                : [];
        } catch (Throwable) {
            $payload = [];
        }

        $debugId = $response->header(
            'PayPal-Debug-Id'
        );

        if (
            !is_string($debugId)
            || trim($debugId) === ''
        ) {
            $debugId = isset(
                $payload['debug_id']
            ) && is_string(
                $payload['debug_id']
            )
                ? $payload['debug_id']
                : null;
        }

        $name = isset(
            $payload['name']
        ) && is_string(
            $payload['name']
        )
            ? $payload['name']
            : null;

        $message = isset(
            $payload['message']
        ) && is_string(
            $payload['message']
        )
            ? $payload['message']
            : $fallbackMessage;

        $details = isset(
            $payload['details']
        ) && is_array(
            $payload['details']
        )
            ? $payload['details']
            : null;

        return new PayPalApiException(
            message: $message,

            statusCode:
                $response->status(),

            debugId:
                $debugId,

            paypalErrorName:
                $name,

            details:
                $details,
        );
    }
}
