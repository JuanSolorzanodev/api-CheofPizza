<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Requests\Api\V1\Admin\Catalog\UpdateCategoryPricesRequest;
use App\Http\Resources\Api\V1\Admin\Catalog\AdminCategoryPriceResource;
use App\Services\Admin\Catalog\AdminCatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CategoryPriceController
{
    public function index(
        AdminCatalogService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: AdminCategoryPriceResource::collection(
                $service->categoryPrices()
            ),

            message: 'Precios por categoría recuperados correctamente.'
        );
    }

    public function update(
        UpdateCategoryPricesRequest $request,
        AdminCatalogService $service
    ): JsonResponse {
        /** @var array<int, array<string, mixed>> $prices */
        $prices = $request->validated(
            'prices'
        );

        $updatedPrices =
            $service->updateCategoryPrices(
                $prices
            );

        return ApiResponse::success(
            data: AdminCategoryPriceResource::collection(
                $updatedPrices
            ),

            message: 'Precios actualizados correctamente.'
        );
    }
}
