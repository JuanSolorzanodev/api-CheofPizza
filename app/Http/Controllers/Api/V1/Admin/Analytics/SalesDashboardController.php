<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Http\Requests\Api\V1\Admin\Analytics\AnalyticsDateRangeRequest;
use App\Services\Admin\Analytics\SalesAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SalesDashboardController
{
    public function __invoke(
        AnalyticsDateRangeRequest $request,
        SalesAnalyticsService $service,
    ): JsonResponse {
        $range =
            AnalyticsDateRangeData::fromValidated(
                $request->validated()
            );

        return ApiResponse::success(
            data:
                $service->dashboard(
                    $range
                ),

            message:
                'Resumen de ventas recuperado correctamente.'
        );
    }
}
