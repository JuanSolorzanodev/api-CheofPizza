<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Http\Requests\Api\V1\Admin\Analytics\AnalyticsDateRangeRequest;
use App\Services\Admin\Analytics\PaymentAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PaymentAnalyticsController
{
    public function __invoke(
        AnalyticsDateRangeRequest $request,
        PaymentAnalyticsService $service,
    ): JsonResponse {
        $range =
            AnalyticsDateRangeData::fromValidated(
                $request->validated()
            );

        return ApiResponse::success(
            data: $service->get($range),
            message:
                'Resumen financiero recuperado correctamente.',
        );
    }
}
