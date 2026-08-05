<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Requests\Api\V1\Admin\Catalog\UpdateIngredientPricesRequest;
use App\Http\Resources\Api\V1\Admin\Catalog\AdminIngredientPriceResource;
use App\Models\Ingredient;
use App\Services\Admin\Catalog\AdminIngredientService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class IngredientPriceController
{
    public function index(
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: AdminIngredientPriceResource::collection(
                $service->prices()
            ),

            message: 'Precios extra recuperados correctamente.'
        );
    }

    public function update(
        UpdateIngredientPricesRequest $request,
        Ingredient $ingredient,
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: AdminIngredientPriceResource::collection(
                $service->updatePrices(
                    $ingredient,
                    $request->validated()
                )
            ),

            message: 'Precios extra actualizados correctamente.'
        );
    }
}
