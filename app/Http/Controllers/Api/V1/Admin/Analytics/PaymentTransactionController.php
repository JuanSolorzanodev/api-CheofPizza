<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Http\Requests\Api\V1\Admin\Analytics\PaymentTransactionIndexRequest;
use App\Services\Admin\Analytics\PaymentTransactionAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PaymentTransactionController
{
    public function __invoke(
        PaymentTransactionIndexRequest $request,
        PaymentTransactionAnalyticsService $service,
    ): JsonResponse {
        $validated =
            $request->validated();

        $range =
            AnalyticsDateRangeData::fromValidated(
                $validated,
            );

        $filters = [
            'method' =>
                $validated['method']
                ?? null,

            'status' =>
                $validated['status']
                ?? null,

            'search' =>
                $validated['search']
                ?? null,

            'page' =>
                (int) $validated['page'],

            'per_page' =>
                (int) $validated['per_page'],
        ];

        $paginator =
            $service->paginate(
                range: $range,
                filters: $filters,
            );

        $summary =
            $service->summary(
                range: $range,
                filters: $filters,
            );

        return ApiResponse::success(
            data: [
                'period' =>
                    $range->toArray(),

                'filters' => [
                    'method' =>
                        $validated['method']
                        ?? null,

                    'status' =>
                        $validated['status']
                        ?? null,

                    'search' =>
                        $validated['search']
                        ?? null,
                ],

                'summary' =>
                    $summary,

                'transactions' =>
                    $paginator->items(),
            ],

            message:
                'Transacciones financieras recuperadas correctamente.',

            meta: [
                'current_page' =>
                    $paginator->currentPage(),

                'per_page' =>
                    $paginator->perPage(),

                'last_page' =>
                    $paginator->lastPage(),

                'total' =>
                    $paginator->total(),

                'from' =>
                    $paginator->firstItem(),

                'to' =>
                    $paginator->lastItem(),
            ],
        );
    }
}
