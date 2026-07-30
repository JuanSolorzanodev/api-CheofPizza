<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Requests\Api\V1\Admin\Catalog\StoreIngredientTypeRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\UpdateIngredientTypeRequest;
use App\Http\Resources\Api\V1\Admin\Catalog\AdminIngredientTypeResource;
use App\Models\IngredientType;
use App\Services\Admin\Catalog\AdminIngredientService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class IngredientTypeController
{
    public function index(
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                AdminIngredientTypeResource::collection(
                    $service->ingredientTypes()
                ),

            message:
                'Tipos de ingredientes recuperados correctamente.'
        );
    }

    public function store(
        StoreIngredientTypeRequest $request,
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new AdminIngredientTypeResource(
                    $service->createIngredientType(
                        $request->validated()
                    )
                ),

            message:
                'Tipo de ingrediente creado correctamente.',

            status: 201
        );
    }

    public function show(
        IngredientType $ingredientType,
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new AdminIngredientTypeResource(
                    $service->ingredientType(
                        $ingredientType
                    )
                ),

            message:
                'Tipo de ingrediente recuperado correctamente.'
        );
    }

    public function update(
        UpdateIngredientTypeRequest $request,
        IngredientType $ingredientType,
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new AdminIngredientTypeResource(
                    $service->updateIngredientType(
                        $ingredientType,
                        $request->validated()
                    )
                ),

            message:
                'Tipo de ingrediente actualizado correctamente.'
        );
    }

    public function destroy(
        IngredientType $ingredientType,
        AdminIngredientService $service
    ): JsonResponse {
        $service->deleteIngredientType(
            $ingredientType
        );

        return ApiResponse::success(
            data: null,
            message:
                'Tipo de ingrediente eliminado correctamente.'
        );
    }
}
