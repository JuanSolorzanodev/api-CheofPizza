<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Contracts\MachineLearning\MachineLearningClientContract;
use App\Exceptions\MachineLearningServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class MachineLearningClient implements MachineLearningClientContract
{
    private const METHOD_GET = 'get';

    private const METHOD_POST = 'post';

    /**
     * Consulta información pública del modelo activo.
     *
     * @return array<string, mixed>
     */
    public function model(): array
    {
        return $this->send(
            method: self::METHOD_GET,
            endpoint: '/api/v1/model',
        );
    }

    /**
     * Consulta el registro persistente del modelo activo,
     * su historial y la disponibilidad de rollback.
     *
     * @return array<string, mixed>
     */
    public function registry(): array
    {
        return $this->send(
            method: self::METHOD_GET,
            endpoint: '/api/v1/models/registry',
        );
    }

    /**
     * Solicita un pronóstico al modelo actualmente activo.
     *
     * @return array<string, mixed>
     */
    public function predict(
        string $startDate,
        int $days,
    ): array {
        return $this->send(
            method: self::METHOD_POST,
            endpoint: '/api/v1/predict',
            payload: [
                'start_date' => $startDate,
                'days' => $days,
            ],
        );
    }

    /**
     * Valida el contrato y consistencia del dataset
     * sin ejecutar entrenamiento.
     *
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public function validateTrainingDataset(
        array $dataset,
    ): array {
        return $this->send(
            method: self::METHOD_POST,
            endpoint: '/api/v1/training/validate',
            payload: $dataset,
            trainingRequest: true,
        );
    }

    /**
     * Compara los algoritmos candidatos mediante
     * validación temporal sin guardar artefactos.
     *
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public function previewTraining(
        array $dataset,
    ): array {
        return $this->send(
            method: self::METHOD_POST,
            endpoint: '/api/v1/training/preview',
            payload: $dataset,
            trainingRequest: true,
        );
    }

    /**
     * Entrena el algoritmo ganador y construye
     * un artefacto candidato persistente en FastAPI.
     *
     * Esta operación no activa automáticamente el modelo.
     *
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    public function buildTrainingArtifact(
        array $dataset,
    ): array {
        return $this->send(
            method: self::METHOD_POST,
            endpoint: '/api/v1/training/build',
            payload: $dataset,
            trainingRequest: true,
        );
    }

    /**
     * Activa un candidato histórico ya construido.
     *
     * @return array<string, mixed>
     */
    public function activateModel(
        string $artifactId,
    ): array {
        $normalizedArtifactId = trim(
            $artifactId,
        );

        if ($normalizedArtifactId === '') {
            throw new MachineLearningServiceException(
                'El identificador del artefacto es obligatorio.',
            );
        }

        return $this->send(
            method: self::METHOD_POST,
            endpoint: sprintf(
                '/api/v1/models/%s/activate',
                rawurlencode(
                    $normalizedArtifactId,
                ),
            ),
        );
    }

    /**
     * Restaura el modelo inmediatamente anterior
     * registrado por FastAPI.
     *
     * @return array<string, mixed>
     */
    public function rollbackModel(): array
    {
        return $this->send(
            method: self::METHOD_POST,
            endpoint: '/api/v1/models/rollback',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(
        string $method,
        string $endpoint,
        array $payload = [],
        bool $trainingRequest = false,
    ): array {
        try {
            $request = $this->request(
                trainingRequest: $trainingRequest,
            );

            $response = match ($method) {
                self::METHOD_GET => $request->get(
                    $endpoint,
                ),

                self::METHOD_POST => $request->post(
                    $endpoint,
                    $payload,
                ),

                default => throw new MachineLearningServiceException(
                    'Método HTTP no soportado para el servicio predictivo.',
                ),
            };
        } catch (ConnectionException $exception) {
            throw new MachineLearningServiceException(
                message: 'No fue posible establecer conexión con el servicio predictivo.',

                previous: $exception,
            );
        } catch (RequestException $exception) {
            throw new MachineLearningServiceException(
                message: 'La comunicación con el servicio predictivo fue interrumpida.',

                remoteStatus: $exception->response?->status(),

                remotePayload: $this->responsePayload(
                    $exception->response,
                ),

                previous: $exception,
            );
        } catch (MachineLearningServiceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MachineLearningServiceException(
                message: 'Ocurrió un error inesperado al consultar el servicio predictivo.',

                previous: $exception,
            );
        }

        return $this->validateResponse(
            $response,
        );
    }

    private function request(
        bool $trainingRequest,
    ): PendingRequest {
        $baseUrl = trim(
            (string) config(
                'services.machine_learning.base_url',
            ),
        );

        $apiKey = trim(
            (string) config(
                'services.machine_learning.api_key',
            ),
        );

        if ($baseUrl === '') {
            throw new MachineLearningServiceException(
                'ML_SERVICE_URL no está configurado.',
            );
        }

        if ($apiKey === '') {
            throw new MachineLearningServiceException(
                'ML_SERVICE_API_KEY no está configurado.',
            );
        }

        $timeout = $trainingRequest
            ? (int) config(
                'services.machine_learning.training_timeout',
                180,
            )
            : (int) config(
                'services.machine_learning.timeout',
                30,
            );

        $connectTimeout = (int) config(
            'services.machine_learning.connect_timeout',
            10,
        );

        $retryTimes = (int) config(
            'services.machine_learning.retry_times',
            3,
        );

        $retrySleepMilliseconds = (int) config(
            'services.machine_learning.retry_sleep_ms',
            500,
        );

        return Http::baseUrl(
            rtrim(
                $baseUrl,
                '/',
            ),
        )
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-ML-API-Key' => $apiKey,
            ])
            ->connectTimeout(
                max(
                    1,
                    $connectTimeout,
                ),
            )
            ->timeout(
                max(
                    1,
                    $timeout,
                ),
            )
            ->retry(
                times: max(
                    1,
                    $retryTimes,
                ),

                sleepMilliseconds: max(
                    0,
                    $retrySleepMilliseconds,
                ),

                when: static function (
                    Throwable $exception,
                    PendingRequest $request,
                ): bool {
                    unset(
                        $request,
                    );

                    return $exception instanceof ConnectionException;
                },

                throw: false,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateResponse(
        Response $response,
    ): array {
        $payload = $this->responsePayload(
            $response,
        );

        if (! $response->successful()) {
            $remoteMessage = $this->remoteMessage(
                $payload,
            );

            throw new MachineLearningServiceException(
                message: $remoteMessage
                    ?? 'El servicio predictivo respondió con un error.',

                remoteStatus: $response->status(),

                remotePayload: $payload,
            );
        }

        if ($payload === null) {
            throw new MachineLearningServiceException(
                message: 'El servicio predictivo devolvió una respuesta inválida.',

                remoteStatus: $response->status(),
            );
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function responsePayload(
        ?Response $response,
    ): ?array {
        if ($response === null) {
            return null;
        }

        $payload = $response->json();

        return is_array(
            $payload,
        )
            ? $payload
            : null;
    }

    /**
     * Extrae un mensaje comprensible de los errores devueltos
     * por FastAPI, incluyendo validaciones Pydantic.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function remoteMessage(
        ?array $payload,
    ): ?string {
        if ($payload === null) {
            return null;
        }

        $detail = $payload[
            'detail'
        ] ?? null;

        if (is_string($detail)) {
            return $detail;
        }

        $message = $payload[
            'message'
        ] ?? null;

        if (is_string($message)) {
            return $message;
        }

        if (! is_array($detail)) {
            return null;
        }

        $messages = collect(
            $detail,
        )
            ->filter(
                static fn (mixed $error): bool => is_array($error),
            )
            ->map(
                static function (
                    array $error,
                ): string {
                    $location = collect(
                        $error['loc'] ?? [],
                    )
                        ->map(
                            static fn (mixed $segment): string => (string) $segment,
                        )
                        ->implode(
                            '.',
                        );

                    $errorMessage = is_string(
                        $error['msg'] ?? null,
                    )
                        ? $error['msg']
                        : 'Dato inválido.';

                    return $location !== ''
                        ? sprintf(
                            '%s: %s',
                            $location,
                            $errorMessage,
                        )
                        : $errorMessage;
                },
            )
            ->values()
            ->all();

        return $messages === []
            ? null
            : implode(
                ' ',
                $messages,
            );
    }
}
