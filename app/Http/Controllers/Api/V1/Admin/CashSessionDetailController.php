<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\CashSession;
use App\Services\Admin\CashRegister\CashSessionDetailService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CashSessionDetailController
{
    public function __invoke(
        CashSession $cashSession,
        CashSessionDetailService $service,
    ): JsonResponse {
        return ApiResponse::success(
            data: $service->get(
                $cashSession
            ),

            message: 'Detalle de caja recuperado correctamente.',
        );
    }
}
