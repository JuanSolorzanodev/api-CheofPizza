<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\CashSession;
use App\Services\Admin\CashRegister\CashSessionSummaryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CashSessionSummaryController
{
    public function __invoke(
        CashSession $cashSession,
        CashSessionSummaryService $service,
    ): JsonResponse {
        $cashSession->load([
            'openedBy',
            'closedBy',
        ]);

        return ApiResponse::success(
            data:
                $service->get(
                    $cashSession
                ),

            message:
                'Resumen de caja recuperado correctamente.',
        );
    }
}
