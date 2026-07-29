<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\MachineLearning\ImportForecastRequest;
use App\Http\Resources\Api\V1\Admin\MachineLearning\MlModelRunResource;
use App\Models\MlModelRun;
use App\Models\User;
use App\Services\MachineLearning\ForecastImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MachineLearningController
{
    public function latest(): JsonResponse
    {
        $run = MlModelRun::query()
            ->with([
                'predictions' => static fn ($query) =>
                    $query->orderBy('prediction_date'),

                'creator.role',
            ])
            ->where(
                'status',
                MlModelRun::STATUS_COMPLETED
            )
            ->where('is_active', true)
            ->first();

        if ($run === null) {
            return ApiResponse::success(
                data: null,
                message:
                    'Todavía no existe un pronóstico predictivo activo.'
            );
        }

        return ApiResponse::success(
            data: new MlModelRunResource($run),
            message:
                'Pronóstico activo recuperado correctamente.'
        );
    }

    public function history(
        Request $request
    ): JsonResponse {
        $perPage = max(
            1,
            min(
                50,
                $request->integer(
                    'per_page',
                    15
                )
            )
        );

        $runs = MlModelRun::query()
            ->with('creator.role')
            ->latest('generated_at')
            ->paginate($perPage);

        return ApiResponse::success(
            data: MlModelRunResource::collection(
                $runs->getCollection()
            ),

            message:
                'Historial de modelos recuperado correctamente.',

            meta: [
                'current_page' =>
                    $runs->currentPage(),

                'last_page' =>
                    $runs->lastPage(),

                'per_page' =>
                    $runs->perPage(),

                'total' =>
                    $runs->total(),
            ]
        );
    }

    public function show(
        string $uuid
    ): JsonResponse {
        $run = MlModelRun::query()
            ->with([
                'predictions' => static fn ($query) =>
                    $query->orderBy('prediction_date'),

                'creator.role',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ApiResponse::success(
            data: new MlModelRunResource($run),
            message:
                'Ejecución predictiva recuperada correctamente.'
        );
    }

    public function import(
        ImportForecastRequest $request,
        ForecastImportService $service
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();

        $run = $service->import(
            payload: $request->validated(),
            admin: $admin
        );

        return ApiResponse::success(
            data: new MlModelRunResource($run),
            message:
                'Pronóstico importado correctamente.',
            status: 201
        );
    }
}
