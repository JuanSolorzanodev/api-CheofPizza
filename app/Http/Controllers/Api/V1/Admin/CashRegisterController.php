<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\CashRegister\CashSessionHistoryRequest;
use App\Http\Requests\Api\V1\Admin\CashRegister\CloseCashSessionRequest;
use App\Http\Requests\Api\V1\Admin\CashRegister\OpenCashSessionRequest;
use App\Http\Requests\Api\V1\Admin\CashRegister\StoreCashMovementRequest;
use App\Http\Resources\Api\V1\Admin\CashRegister\CashMovementResource;
use App\Http\Resources\Api\V1\Admin\CashRegister\CashSessionResource;
use App\Models\CashSession;
use App\Models\User;
use App\Services\Admin\CashRegister\CashRegisterService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CashRegisterController
{
    public function current(
        CashRegisterService $service,
    ): JsonResponse {
        $session = $service->current();

        return ApiResponse::success(
            data: $session !== null
                    ? new CashSessionResource($session)
                    : null,

            message: $session !== null
                    ? 'Caja abierta recuperada correctamente.'
                    : 'No existe una caja abierta.',
        );
    }

    public function open(
        OpenCashSessionRequest $request,
        CashRegisterService $service,
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();

        return ApiResponse::success(
            data: new CashSessionResource(
                $service->open(
                    admin: $admin,
                    data: $request->validated(),
                )
            ),

            message: 'Caja abierta correctamente.',

            status: 201,
        );
    }

    public function storeMovement(
        StoreCashMovementRequest $request,
        CashSession $cashSession,
        CashRegisterService $service,
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();

        return ApiResponse::success(
            data: new CashMovementResource(
                $service->addMovement(
                    session: $cashSession,
                    admin: $admin,
                    data: $request->validated(),
                )
            ),

            message: 'Movimiento de caja registrado correctamente.',

            status: 201,
        );
    }

    public function movements(
        Request $request,
        CashSession $cashSession,
        CashRegisterService $service,
    ): JsonResponse {
        $perPage = min(
            max(
                (int) $request->integer(
                    'per_page',
                    15,
                ),
                1,
            ),
            100,
        );

        $paginator = $service->movements(
            session: $cashSession,
            perPage: $perPage,
        );

        return ApiResponse::success(
            data: CashMovementResource::collection(
                $paginator->items()
            ),

            message: 'Movimientos de caja recuperados correctamente.',

            meta: [
                'current_page' => $paginator->currentPage(),

                'per_page' => $paginator->perPage(),

                'last_page' => $paginator->lastPage(),

                'total' => $paginator->total(),

                'from' => $paginator->firstItem(),

                'to' => $paginator->lastItem(),
            ],
        );
    }

    public function close(
        CloseCashSessionRequest $request,
        CashSession $cashSession,
        CashRegisterService $service,
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();

        return ApiResponse::success(
            data: new CashSessionResource(
                $service->close(
                    session: $cashSession,
                    admin: $admin,
                    data: $request->validated(),
                )
            ),

            message: 'Caja cerrada correctamente.',
        );
    }

    public function history(
        CashSessionHistoryRequest $request,
        CashRegisterService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $paginator = $service->history([
            'date_from' => $validated['date_from'] ?? null,

            'date_to' => $validated['date_to'] ?? null,

            'status' => $validated['status'] ?? null,

            'page' => (int) $validated['page'],

            'per_page' => (int) $validated['per_page'],
        ]);

        return ApiResponse::success(
            data: CashSessionResource::collection(
                $paginator->items()
            ),

            message: 'Historial de caja recuperado correctamente.',

            meta: [
                'current_page' => $paginator->currentPage(),

                'per_page' => $paginator->perPage(),

                'last_page' => $paginator->lastPage(),

                'total' => $paginator->total(),

                'from' => $paginator->firstItem(),

                'to' => $paginator->lastItem(),
            ],
        );
    }
}
