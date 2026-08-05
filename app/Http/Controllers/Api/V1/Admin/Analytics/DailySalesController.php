<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Http\Requests\Api\V1\Admin\Analytics\AnalyticsDateRangeRequest;
use App\Services\Admin\Analytics\DailySalesAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DailySalesController
{
    public function __invoke(
        AnalyticsDateRangeRequest $request,
        DailySalesAnalyticsService $service,
    ): JsonResponse {
        $range =
            AnalyticsDateRangeData::fromValidated(
                $request->validated()
            );

        return ApiResponse::success(
            data: $service->get($range),
            message: 'Ventas diarias recuperadas correctamente.',
        );
    }
}
