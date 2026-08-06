<?php

declare(strict_types=1);

namespace App\Support\MachineLearning;

use App\Exceptions\MachineLearningServiceException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class MachineLearningErrorResponder
{
    public function forecast(
        MachineLearningServiceException $exception,
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

    public function training(
        MachineLearningServiceException $exception,
    ): JsonResponse {
        $remoteStatus = $exception->remoteStatus();

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
