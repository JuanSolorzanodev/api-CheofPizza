<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\UpdateBusinessSettingRequest;
use App\Http\Resources\Api\V1\Admin\BusinessSettingResource;
use App\Services\Settings\BusinessSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SettingController
{
    public function show(
        BusinessSettingService $service,
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new BusinessSettingResource(
                    $service->current(),
                    $service->whatsapp(),
                ),

            message:
                'Configuración recuperada correctamente.',
        );
    }

    public function update(
        UpdateBusinessSettingRequest $request,
        BusinessSettingService $service,
    ): JsonResponse {
        $setting =
            $service->update(
                $request->validated(),
            );

        return ApiResponse::success(
            data:
                new BusinessSettingResource(
                    $setting,
                    $service->whatsapp(),
                ),

            message:
                'Configuración actualizada correctamente.',
        );
    }
}
