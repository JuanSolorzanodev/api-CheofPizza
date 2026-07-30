<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Requests\Api\V1\Admin\Catalog\StoreSizeRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\UpdateSizeRequest;
use App\Http\Resources\Api\V1\Admin\Catalog\AdminSizeResource;
use App\Models\Size;
use App\Services\Admin\Catalog\AdminCatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SizeController
{
    public function index(
        AdminCatalogService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                AdminSizeResource::collection(
                    $service->sizes()
                ),

            message:
                'Tamaños recuperados correctamente.'
        );
    }

    public function store(
        StoreSizeRequest $request,
        AdminCatalogService $service
    ): JsonResponse {
        $size = $service->createSize(
            $request->validated()
        );

        return ApiResponse::success(
            data:
                new AdminSizeResource(
                    $size
                ),

            message:
                'Tamaño creado correctamente.',

            status: 201
        );
    }

    public function show(
        Size $size,
        AdminCatalogService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new AdminSizeResource(
                    $service->size($size)
                ),

            message:
                'Tamaño recuperado correctamente.'
        );
    }

    public function update(
        UpdateSizeRequest $request,
        Size $size,
        AdminCatalogService $service
    ): JsonResponse {
        $size = $service->updateSize(
            size: $size,
            data: $request->validated(),
        );

        return ApiResponse::success(
            data:
                new AdminSizeResource(
                    $size
                ),

            message:
                'Tamaño actualizado correctamente.'
        );
    }

    public function destroy(
        Size $size,
        AdminCatalogService $service
    ): JsonResponse {
        $service->deleteSize($size);

        return ApiResponse::success(
            data: null,
            message:
                'Tamaño eliminado correctamente.'
        );
    }
}
