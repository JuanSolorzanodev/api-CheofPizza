<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Exceptions\MachineLearningServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class MachineLearningClient
{
    /**
     * Consulta información del modelo actualmente desplegado.
     *
     * @return array<string, mixed>
     */
    public function model(): array
    {
        return $this->send(
            method: 'get',
            endpoint: '/api/v1/model',
        );
    }

    /**
     * Solicita un pronóstico dinámico al microservicio.
     *
     * @return array<string, mixed>
     */
    public function predict(
        string $startDate,
        int $days,
    ): array {
        return $this->send(
            method: 'post',
            endpoint: '/api/v1/predict',
            payload: [
                'start_date' => $startDate,
                'days' => $days,
            ],
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $endpoint,
        array $payload = [],
    ): array {
        try {
            $request = $this->request();

            $response = match ($method) {
                'get' => $request->get($endpoint),

                'post' => $request->post(
                    $endpoint,
                    $payload,
                ),

                default => throw new MachineLearningServiceException(
                    'Método HTTP no soportado para el servicio predictivo.'
                ),
            };
        } catch (ConnectionException $exception) {
            throw new MachineLearningServiceException(
                message:
                    'No fue posible establecer conexión con el servicio predictivo.',
                previous: $exception,
            );
        } catch (MachineLearningServiceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MachineLearningServiceException(
                message:
                    'Ocurrió un error inesperado al consultar el servicio predictivo.',
                previous: $exception,
            );
        }

        return $this->validateResponse(
            $response
        );
    }

    private function request(): PendingRequest
    {
        $baseUrl = trim(
            (string) config(
                'services.machine_learning.base_url'
            )
        );

        $apiKey = trim(
            (string) config(
                'services.machine_learning.api_key'
            )
        );

        if ($baseUrl === '') {
            throw new MachineLearningServiceException(
                'ML_SERVICE_URL no está configurado.'
            );
        }

        if ($apiKey === '') {
            throw new MachineLearningServiceException(
                'ML_SERVICE_API_KEY no está configurado.'
            );
        }

        return Http::baseUrl(
            rtrim($baseUrl, '/')
        )
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-ML-API-Key' => $apiKey,
            ])
            ->connectTimeout(
                max(
                    1,
                    (int) config(
                        'services.machine_learning.connect_timeout',
                        5
                    )
                )
            )
            ->timeout(
                max(
                    1,
                    (int) config(
                        'services.machine_learning.timeout',
                        20
                    )
                )
            )
            ->retry(
                times: 3,
                sleepMilliseconds: 300,
                throw: false,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateResponse(
        Response $response
    ): array {
        $payload = $response->json();

        if (!$response->successful()) {
            $remoteMessage = is_array($payload)
                ? (
                    $payload['detail']
                    ?? $payload['message']
                    ?? null
                )
                : null;

            throw new MachineLearningServiceException(
                message: is_string($remoteMessage)
                    ? $remoteMessage
                    : 'El servicio predictivo respondió con un error.',

                remoteStatus: $response->status(),

                remotePayload: is_array($payload)
                    ? $payload
                    : null,
            );
        }

        if (!is_array($payload)) {
            throw new MachineLearningServiceException(
                message:
                    'El servicio predictivo devolvió una respuesta inválida.',
                remoteStatus: $response->status(),
            );
        }

        return $payload;
    }
}
