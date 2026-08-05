<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Requests\Api\V1\Admin\Catalog\StoreIngredientRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\UpdateIngredientRequest;
use App\Http\Resources\Api\V1\Admin\Catalog\AdminIngredientResource;
use App\Models\Ingredient;
use App\Services\Admin\Catalog\AdminIngredientService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class IngredientController
{
    public function index(
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: AdminIngredientResource::collection(
                $service->ingredients()
            ),

            message: 'Ingredientes recuperados correctamente.'
        );
    }

    public function store(
        StoreIngredientRequest $request,
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: new AdminIngredientResource(
                $service->createIngredient(
                    $request->validated()
                )
            ),

            message: 'Ingrediente creado correctamente.',

            status: 201
        );
    }

    public function show(
        Ingredient $ingredient,
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: new AdminIngredientResource(
                $service->ingredient(
                    $ingredient
                )
            ),

            message: 'Ingrediente recuperado correctamente.'
        );
    }

    public function update(
        UpdateIngredientRequest $request,
        Ingredient $ingredient,
        AdminIngredientService $service
    ): JsonResponse {
        return ApiResponse::success(
            data: new AdminIngredientResource(
                $service->updateIngredient(
                    $ingredient,
                    $request->validated()
                )
            ),

            message: 'Ingrediente actualizado correctamente.'
        );
    }

    public function destroy(
        Ingredient $ingredient,
        AdminIngredientService $service
    ): JsonResponse {
        $service->deleteIngredient(
            $ingredient
        );

        return ApiResponse::success(
            data: null,
            message: 'Ingrediente eliminado correctamente.'
        );
    }
}
