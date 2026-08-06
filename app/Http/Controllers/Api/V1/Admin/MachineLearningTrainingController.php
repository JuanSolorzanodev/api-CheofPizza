<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\MachineLearningServiceException;
use App\Http\Requests\Admin\MachineLearning\TrainingDatasetRequest;
use App\Http\Resources\Api\V1\Admin\MachineLearning\MlTrainingRunResource;
use App\Models\MlTrainingRun;
use App\Models\User;
use App\Services\MachineLearning\MachineLearningTrainingRunQueryService;
use App\Services\MachineLearning\TrainingWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MachineLearningTrainingController
{
    public function registry(
        TrainingWorkflowService $service,
    ): JsonResponse {
        try {
            return ApiResponse::success(
                data: $service->registry(),

                message: 'Registro remoto de modelos recuperado correctamente.',
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception,
            );
        }
    }

    public function index(
        Request $request,
        MachineLearningTrainingRunQueryService $queryService,
    ): JsonResponse {
        $perPage = max(
            1,
            min(
                50,
                $request->integer(
                    'per_page',
                    15,
                ),
            ),
        );

        $runs = $queryService->paginate(
            perPage: $perPage,
        );

        return ApiResponse::success(
            data: MlTrainingRunResource::collection(
                $runs->getCollection(),
            ),

            message: 'Historial de entrenamientos recuperado correctamente.',

            meta: [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        );
    }

    public function show(
        MlTrainingRun $trainingRun,
        MachineLearningTrainingRunQueryService $queryService,
    ): JsonResponse {
        $trainingRun = $queryService->loadDetails(
            $trainingRun,
        );

        return ApiResponse::success(
            data: new MlTrainingRunResource(
                $trainingRun,
            ),

            message: 'Previsualización de entrenamiento generada correctamente.',
        );
    }

    public function preview(
        TrainingDatasetRequest $request,
        TrainingWorkflowService $service,
    ): JsonResponse {
        try {
            return ApiResponse::success(
                data: $service->preview(
                    $request->validated(),
                ),

                message: 'Previsualización de entrenamiento generada correctamente.',
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception,
            );
        }
    }

    public function build(
        TrainingDatasetRequest $request,
        TrainingWorkflowService $service,
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();

        try {
            $run = $service->build(
                options: $request->validated(),

                admin: $admin,
            );

            return ApiResponse::success(
                data: new MlTrainingRunResource(
                    $run,
                ),

                message: 'Candidato de entrenamiento construido correctamente.',

                status: 201,
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception,
            );
        }
    }

    public function activate(
        MlTrainingRun $trainingRun,
        TrainingWorkflowService $service,
    ): JsonResponse {
        try {
            $result = $service->activate(
                $trainingRun,
            );

            return ApiResponse::success(
                data: [
                    'registry' => $result['remote'],

                    'training_run' => new MlTrainingRunResource(
                        $result['training_run'],
                    ),
                ],

                message: 'Modelo candidato activado correctamente.',
            );
        } catch (
            RuntimeException $exception
        ) {
            return ApiResponse::error(
                message: $exception->getMessage(),

                status: 409,

                code: 'ML_TRAINING_RUN_NOT_ACTIVATABLE',
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception,
            );
        }
    }

    public function rollback(
        TrainingWorkflowService $service,
    ): JsonResponse {
        try {
            $result = $service->rollback();

            return ApiResponse::success(
                data: [
                    'registry' => $result['remote'],

                    'training_run' => $result['training_run'] === null
                        ? null
                        : new MlTrainingRunResource(
                            $result['training_run'],
                        ),
                ],

                message: 'Rollback del modelo ejecutado correctamente.',
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            return $this->serviceError(
                $exception,
            );
        }
    }

    private function serviceError(
        MachineLearningServiceException $exception,
    ): JsonResponse {
        $remoteStatus =
            $exception->remoteStatus();

        $status = match ($remoteStatus) {
            409 => 409,
            422 => 422,
            401, 403 => 502,
            default => 503,
        };

        $code = match ($remoteStatus) {
            409 => 'ML_MODEL_ROLLBACK_UNAVAILABLE',

            422 => 'ML_SERVICE_VALIDATION_FAILED',

            default => 'ML_SERVICE_UNAVAILABLE',
        };

        return ApiResponse::error(
            message: $exception->getMessage(),

            status: $status,

            code: $code,
        );
    }
}
