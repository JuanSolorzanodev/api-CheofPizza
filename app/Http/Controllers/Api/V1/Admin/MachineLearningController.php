<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\MachineLearningServiceException;
use App\Http\Requests\Admin\MachineLearning\GenerateForecastRequest;
use App\Http\Requests\Admin\MachineLearning\ImportForecastRequest;
use App\Http\Resources\Api\V1\Admin\MachineLearning\MlModelRunResource;
use App\Models\MlModelRun;
use App\Models\User;
use App\Services\MachineLearning\ForecastImportService;
use App\Services\MachineLearning\MachineLearningClient;
use App\Services\MachineLearning\RemoteForecastPersistenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Admin\MachineLearning\TrainingDatasetRequest;
use App\Services\MachineLearning\Dataset\MlTrainingDatasetService;
use Illuminate\Http\Request;

final class MachineLearningController
{
    public function latest(): JsonResponse
    {
        $run = MlModelRun::query()
            ->with([
                'predictions' => static fn ($query) =>
                    $query->orderBy('prediction_date'),

                'creator.role',
            ])
            ->where(
                'status',
                MlModelRun::STATUS_COMPLETED
            )
            ->where('is_active', true)
            ->latest('generated_at')
            ->first();

        if ($run === null) {
            return ApiResponse::success(
                data: null,
                message:
                    'Todavía no existe un pronóstico predictivo activo.'
            );
        }

        return ApiResponse::success(
            data: new MlModelRunResource($run),
            message:
                'Pronóstico activo recuperado correctamente.'
        );
    }

    public function history(
        Request $request
    ): JsonResponse {
        $perPage = max(
            1,
            min(
                50,
                $request->integer(
                    'per_page',
                    15
                )
            )
        );

        $runs = MlModelRun::query()
            ->with('creator.role')
            ->latest('generated_at')
            ->paginate($perPage);

        return ApiResponse::success(
            data: MlModelRunResource::collection(
                $runs->getCollection()
            ),

            message:
                'Historial de modelos recuperado correctamente.',

            meta: [
                'current_page' =>
                    $runs->currentPage(),

                'last_page' =>
                    $runs->lastPage(),

                'per_page' =>
                    $runs->perPage(),

                'total' =>
                    $runs->total(),
            ]
        );
    }

    public function show(
        string $uuid
    ): JsonResponse {
        $run = MlModelRun::query()
            ->with([
                'predictions' => static fn ($query) =>
                    $query->orderBy('prediction_date'),

                'creator.role',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ApiResponse::success(
            data: new MlModelRunResource($run),
            message:
                'Ejecución predictiva recuperada correctamente.'
        );
    }

    /**
     * Comprueba que Laravel pueda consultar
     * el modelo desplegado en FastAPI.
     */
    public function serviceModel(
        MachineLearningClient $client
    ): JsonResponse {
        try {
            $model = $client->model();

            return ApiResponse::success(
                data: $model,
                message:
                    'Modelo remoto recuperado correctamente.'
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception
            );
        }
    }

    /**
     * Solicita un pronóstico remoto sin persistirlo.
     *
     * Este endpoint sirve para previsualización
     * y diagnóstico de la integración.
     */
    public function preview(
        GenerateForecastRequest $request,
        MachineLearningClient $client
    ): JsonResponse {
        try {
            $forecast = $client->predict(
                startDate: (string) $request->validated(
                    'start_date'
                ),

                days: (int) $request->validated(
                    'days'
                ),
            );

            return ApiResponse::success(
                data: $forecast,
                message:
                    'Pronóstico remoto generado correctamente.'
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception
            );
        }
    }

    /**
     * Genera un pronóstico mediante FastAPI
     * y lo persiste en las tablas predictivas.
     */
    public function generate(
        GenerateForecastRequest $request,
        MachineLearningClient $client,
        RemoteForecastPersistenceService $persistenceService,
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();

        try {
            $forecast = $client->predict(
                startDate: (string) $request->validated(
                    'start_date'
                ),

                days: (int) $request->validated(
                    'days'
                ),
            );

            $run = $persistenceService->persist(
                forecast: $forecast,
                admin: $admin,
            );

            $run->loadMissing([
                'predictions' => static fn ($query) =>
                    $query->orderBy('prediction_date'),

                'creator.role',
            ]);

            return ApiResponse::success(
                data: new MlModelRunResource($run),
                message:
                    'Pronóstico generado y guardado correctamente.',
                status: 201
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception
            );
        }
    }

    /**
     * Importa manualmente un pronóstico
     * generado fuera del sistema.
     */
    public function import(
        ImportForecastRequest $request,
        ForecastImportService $service
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();

        $run = $service->import(
            payload: $request->validated(),
            admin: $admin
        );

        $run->loadMissing([
            'predictions' => static fn ($query) =>
                $query->orderBy('prediction_date'),

            'creator.role',
        ]);

        return ApiResponse::success(
            data: new MlModelRunResource($run),
            message:
                'Pronóstico importado correctamente.',
            status: 201
        );
    }

    private function serviceError(
        MachineLearningServiceException $exception
    ): JsonResponse {
        $remoteStatus = $exception->remoteStatus();

        $status = match (true) {
            $remoteStatus === 401,
            $remoteStatus === 403 => 502,

            $remoteStatus === 422 => 422,

            default => 503,
        };

        $code = $remoteStatus === 422
            ? 'ML_SERVICE_VALIDATION_FAILED'
            : 'ML_SERVICE_UNAVAILABLE';

        return ApiResponse::error(
            message: $exception->getMessage(),
            status: $status,
            code: $code,
        );
    }

    /**
 * Expone el dataset consolidado únicamente para diagnóstico
 * administrativo y para validar el futuro payload de entrenamiento.
 *
 * FastAPI no consumirá directamente este endpoint desde Internet.
 * Laravel utilizará el mismo servicio para enviar el dataset mediante
 * una llamada autenticada al endpoint de entrenamiento.
 */
public function dataset(
    TrainingDatasetRequest $request,
    MlTrainingDatasetService $service,
): JsonResponse {
    $validated =
        $request->validated();

    $dataset =
        $service->build(
            dateFrom:
                $validated['date_from']
                ?? null,

            dateTo:
                $validated['date_to']
                ?? null,

            limit:
                (int) (
                    $validated['limit']
                    ?? 365
                ),

            includeEmptyDays:
                filter_var(
                    $validated[
                        'include_empty_days'
                    ] ?? true,
                    FILTER_VALIDATE_BOOL,
                    FILTER_NULL_ON_FAILURE,
                ) ?? true,
        );

    return ApiResponse::success(
        data:
            $dataset,

        message:
            'Dataset de entrenamiento recuperado correctamente.',
    );
}
}
