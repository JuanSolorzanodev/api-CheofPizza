<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Requests\Api\V1\Admin\Catalog\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\Admin\Catalog\AdminCategoryResource;
use App\Models\Category;
use App\Services\Admin\Catalog\AdminCatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function index(
        AdminCatalogService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                AdminCategoryResource::collection(
                    $service->categories()
                ),

            message:
                'Categorías recuperadas correctamente.'
        );
    }

    public function store(
        StoreCategoryRequest $request,
        AdminCatalogService $service
    ): JsonResponse {
        $category = $service
            ->createCategory(
                $request->validated()
            );

        return ApiResponse::success(
            data:
                new AdminCategoryResource(
                    $category
                ),

            message:
                'Categoría creada correctamente.',

            status: 201
        );
    }

    public function show(
        Category $category,
        AdminCatalogService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new AdminCategoryResource(
                    $service->category(
                        $category
                    )
                ),

            message:
                'Categoría recuperada correctamente.'
        );
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        AdminCatalogService $service
    ): JsonResponse {
        $category = $service
            ->updateCategory(
                category: $category,
                data: $request->validated(),
            );

        return ApiResponse::success(
            data:
                new AdminCategoryResource(
                    $category
                ),

            message:
                'Categoría actualizada correctamente.'
        );
    }

    public function destroy(
        Category $category,
        AdminCatalogService $service
    ): JsonResponse {
        $service->deleteCategory(
            $category
        );

        return ApiResponse::success(
            data: null,
            message:
                'Categoría eliminada correctamente.'
        );
    }
}
