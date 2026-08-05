<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\MachineLearning\MachineLearningComparisonRequest;
use App\Services\MachineLearning\Analytics\MachineLearningComparisonService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class MachineLearningComparisonController
{
    public function __invoke(
        MachineLearningComparisonRequest $request,
        MachineLearningComparisonService $service,
    ): JsonResponse {
        $validated =
            $request->validated();

        return ApiResponse::success(
            data: $service->compare(
                dateFrom:
                    (string) $validated[
                        'date_from'
                    ],

                dateTo:
                    (string) $validated[
                        'date_to'
                    ],
            ),

            message:
                'Comparación predictiva recuperada correctamente.',
        );
    }
}
