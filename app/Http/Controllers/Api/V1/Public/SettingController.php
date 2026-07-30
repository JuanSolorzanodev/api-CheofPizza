<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Resources\Api\V1\PublicBusinessSettingResource;
use App\Services\Payments\TransferAccountService;
use App\Services\Settings\BusinessSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SettingController
{
    public function show(
        BusinessSettingService $settingService,
        TransferAccountService $transferAccountService,
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new PublicBusinessSettingResource(
                    $settingService->current(),
                    $settingService->whatsapp(),
                    $transferAccountService
                        ->getActivePrimary(),
                ),

            message:
                'Configuración pública recuperada correctamente.',
        );
    }
}
