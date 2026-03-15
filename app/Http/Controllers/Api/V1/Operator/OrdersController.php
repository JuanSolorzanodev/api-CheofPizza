<?php

namespace App\Http\Controllers\Api\V1\Operator;

use App\Http\Requests\Api\V1\Operator\OperatorOrderIndexRequest;
use App\Http\Requests\Api\V1\Operator\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\Operator\OperatorOrderDetailResource;
use App\Http\Resources\Api\V1\Operator\OperatorOrderListResource;
use App\Services\Order\OperatorOrderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersController
{
    public function __construct(
        private readonly OperatorOrderService $service,
    ) {}

    /**
     * GET /api/v1/operator/orders
     */
    public function index(OperatorOrderIndexRequest $request)
    {
        /** @var LengthAwarePaginator $orders */
        $orders = $this->service->paginate($request->validated());

        // ✅ Cargar relaciones para kitchen_summary sin depender de getCollection()
        $items = $orders->items(); // array de modelos
        if (!empty($items)) {
            $items[0]->newCollection($items)->load([
                'orderItems',
                'orderItems.orderPromotionItems',
            ]);
        }

        return OperatorOrderListResource::collection($orders);
    }

    /**
     * GET /api/v1/operator/orders/{orderId}
     */
    public function show(Request $request, int $orderId): OperatorOrderDetailResource
    {
        $order = $this->service->findOrFail($orderId);

        return new OperatorOrderDetailResource($order);
    }

    /**
     * PATCH /api/v1/operator/orders/{orderId}/status
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): OperatorOrderDetailResource
    {
        $userId = (int) $request->user()->id;

        $order = $this->service->changeStatus(
            $orderId,
            (string) $request->input('to_status'),
            $request->input('note'),
            $userId
        );

        return new OperatorOrderDetailResource($order);
    }

    public function queue(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->queueCounts(),
        ]);
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->allStatuses(),
        ]);
    }
}
