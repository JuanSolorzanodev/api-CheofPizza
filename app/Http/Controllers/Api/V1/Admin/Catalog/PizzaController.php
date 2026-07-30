<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Catalog;

use App\Http\Requests\Api\V1\Admin\Catalog\StorePizzaRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\UpdatePizzaRequest;
use App\Http\Requests\Api\V1\Admin\Catalog\UpdatePizzaVisibilityRequest;
use App\Http\Resources\Api\V1\Admin\Catalog\AdminPizzaResource;
use App\Models\Pizza;
use App\Services\Admin\Catalog\AdminPizzaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PizzaController
{
    public function index(
        AdminPizzaService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                AdminPizzaResource::collection(
                    $service->pizzas()
                ),

            message:
                'Pizzas recuperadas correctamente.'
        );
    }

    public function store(
        StorePizzaRequest $request,
        AdminPizzaService $service
    ): JsonResponse {
        $pizza = $service->create(
            $request->validated()
        );

        return ApiResponse::success(
            data:
                new AdminPizzaResource(
                    $pizza
                ),

            message:
                'Pizza creada correctamente.',

            status: 201
        );
    }

    public function show(
        Pizza $pizza,
        AdminPizzaService $service
    ): JsonResponse {
        return ApiResponse::success(
            data:
                new AdminPizzaResource(
                    $service->pizza(
                        $pizza
                    )
                ),

            message:
                'Pizza recuperada correctamente.'
        );
    }

    public function update(
        UpdatePizzaRequest $request,
        Pizza $pizza,
        AdminPizzaService $service
    ): JsonResponse {
        $pizza = $service->update(
            pizza: $pizza,
            data: $request->validated(),
        );

        return ApiResponse::success(
            data:
                new AdminPizzaResource(
                    $pizza
                ),

            message:
                'Pizza actualizada correctamente.'
        );
    }

    public function updateVisibility(
        UpdatePizzaVisibilityRequest $request,
        Pizza $pizza,
        AdminPizzaService $service
    ): JsonResponse {
        $pizza =
            $service->updateVisibility(
                pizza: $pizza,
                isVisible:
                    (bool) $request->validated(
                        'is_visible'
                    ),
            );

        return ApiResponse::success(
            data:
                new AdminPizzaResource(
                    $pizza
                ),

            message:
                $pizza->is_visible
                    ? 'Pizza visible en el catálogo.'
                    : 'Pizza ocultada del catálogo.'
        );
    }

    public function destroy(
        Pizza $pizza,
        AdminPizzaService $service
    ): JsonResponse {
        $service->delete($pizza);

        return ApiResponse::success(
            data: null,
            message:
                'Pizza eliminada correctamente.'
        );
    }
}
