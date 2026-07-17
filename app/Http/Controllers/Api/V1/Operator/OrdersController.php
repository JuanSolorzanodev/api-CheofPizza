<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operator;

use App\Http\Requests\Api\V1\Operator\OperatorOrderIndexRequest;
use App\Http\Requests\Api\V1\Operator\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\Operator\OperatorOrderDetailResource;
use App\Http\Resources\Api\V1\Operator\OperatorOrderListResource;
use App\Services\Order\OperatorOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrdersController
{
    public function __construct(
        private readonly OperatorOrderService $service,
    ) {
    }

    public function index(
        OperatorOrderIndexRequest $request,
    ): AnonymousResourceCollection {
        $orders = $this->service->paginate(
            $request->validated(),
        );

        /*
         * Los elementos del paginador son modelos Eloquent.
         * Usamos newCollection() para obtener una
         * Illuminate\Database\Eloquent\Collection,
         * que sí dispone del método load().
         */
        $items = $orders->items();

        if ($items !== []) {
            $items[0]
                ->newCollection($items)
                ->load([
                    'orderItems',
                    'orderItems.orderPromotionItems',
                ]);
        }

        return OperatorOrderListResource::collection(
            $orders,
        );
    }

    public function show(
        int $orderId,
    ): OperatorOrderDetailResource {
        return new OperatorOrderDetailResource(
            $this->service->findOrFail(
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

        $order = $this->service->changeStatus(
            orderId: $orderId,
            destinationStatus:
                $request->destinationStatus(),
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
            'data' =>
                $this->service->queueCounts(),
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' =>
                $this->service->allStatuses(),
        ]);
    }
}
