<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\StorePromotionRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePromotionRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePromotionVisibilityRequest;
use App\Http\Resources\Api\V1\Admin\AdminPromotionResource;
use App\Models\Promotion;
use App\Services\Admin\AdminPromotionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PromotionController
{
    public function index(
        AdminPromotionService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: AdminPromotionResource::collection(
                $service->promotions()
            ),

            message: 'Promociones recuperadas correctamente.'
        );
    }

    public function store(
        StorePromotionRequest $request,
        AdminPromotionService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: new AdminPromotionResource(
                $service->create(
                    $request->validated()
                )
            ),

            message: 'Promoción creada correctamente.',

            status: 201
        );
    }

    public function show(
        Promotion $promotion,
        AdminPromotionService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: new AdminPromotionResource(
                $service->promotion(
                    $promotion
                )
            ),

            message: 'Promoción recuperada correctamente.'
        );
    }

    public function update(
        UpdatePromotionRequest $request,
        Promotion $promotion,
        AdminPromotionService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: new AdminPromotionResource(
                $service->update(
                    $promotion,
                    $request->validated()
                )
            ),

            message: 'Promoción actualizada correctamente.'
        );
    }

    public function updateVisibility(
        UpdatePromotionVisibilityRequest $request,
        Promotion $promotion,
        AdminPromotionService $service
    ): JsonResponse {
        $promotion =
            $service->updateVisibility(
                $promotion,
                (bool) $request->validated(
                    'is_active'
                )
            );

        return ApiResponse::success(
            data: new AdminPromotionResource(
                $promotion
            ),

            message: $promotion->is_active
                    ? 'Promoción activada correctamente.'
                    : 'Promoción desactivada correctamente.'
        );
    }

    public function destroy(
        Promotion $promotion,
        AdminPromotionService $service
    ): JsonResponse {
        $service->delete($promotion);

        return ApiResponse::success(
            data: null,
            message: 'Promoción eliminada correctamente.'
        );
    }
}
