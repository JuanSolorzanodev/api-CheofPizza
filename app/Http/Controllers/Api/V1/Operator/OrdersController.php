<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operator;

use App\Http\Requests\Api\V1\Operator\OperatorOrderIndexRequest;
use App\Http\Requests\Api\V1\Operator\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\Operator\OperatorOrderDetailResource;
use App\Http\Resources\Api\V1\Operator\OperatorOrderListResource;
use App\Services\Order\Operator\OperatorOrderQueryService;
use App\Services\Order\Operator\OperatorOrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrdersController
{
    public function __construct(
        private readonly OperatorOrderQueryService $queryService,
        private readonly OperatorOrderStatusService $statusService,
    ) {}

    public function index(
        OperatorOrderIndexRequest $request,
    ): AnonymousResourceCollection {
        $orders = $this->queryService->paginate(
            $request->validated(),
        );

        return OperatorOrderListResource::collection(
            $orders,
        );
    }

    public function show(
        int $orderId,
    ): OperatorOrderDetailResource {
        return new OperatorOrderDetailResource(
            $this->queryService->findOrFail(
                $orderId,
            ),
        );
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        int $orderId,
    ): OperatorOrderDetailResource {
        $userId = (int) $request
            ->user()
            ->getAuthIdentifier();

        $order = $this->statusService->changeStatus(
            orderId: $orderId,
            destinationStatus: $request->destinationStatus(),
            note: $request->note(),
            changedByUserId: $userId,
        );

        return new OperatorOrderDetailResource(
            $order,
        );
    }

    public function queue(): JsonResponse
    {
        return response()->json([
            'data' => $this->queryService->queueCounts(),
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => $this->queryService->allStatuses(),
        ]);
    }
}
