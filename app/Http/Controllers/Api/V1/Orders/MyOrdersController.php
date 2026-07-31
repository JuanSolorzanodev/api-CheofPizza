<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MyOrdersController
{
    /**
     * Lista paginada de pedidos del cliente autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(
            $user === null,
            401,
            'No autenticado.',
        );

        $perPage = min(
            max(
                $request->integer(
                    'per_page',
                    10,
                ),
                1,
            ),
            50,
        );

        $orders = Order::query()
            ->where(
                'user_id',
                (int) $user->id,
            )
            ->with([
                'deliveryType',
                'paymentMethod',
                'orderStatus',
                'latestPayment',
            ])
            ->withCount('orderItems')
            ->latest('ordered_at')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => OrderResource::collection(
                $orders->getCollection(),
            ),

            'meta' => [
                'current_page' => $orders->currentPage(),
                'from' => $orders->firstItem(),
                'last_page' => $orders->lastPage(),
                'path' => $orders->path(),
                'per_page' => $orders->perPage(),
                'to' => $orders->lastItem(),
                'total' => $orders->total(),
            ],

            'links' => [
                'first' => $orders->url(1),

                'last' => $orders->url(
                    $orders->lastPage(),
                ),

                'prev' => $orders->previousPageUrl(),

                'next' => $orders->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Devuelve el detalle de un pedido que pertenece
     * al cliente autenticado.
     */
    public function show(
        Request $request,
        int $orderId,
    ): JsonResponse {
        $user = $request->user();

        abort_if(
            $user === null,
            401,
            'No autenticado.',
        );

        $order = Order::query()
            ->whereKey($orderId)
            ->where(
                'user_id',
                (int) $user->id,
            )
            ->with([
                'user',
                'deliveryType',
                'paymentMethod',
                'orderStatus',
                'latestPayment',

                'orderItems',
                'orderItems.orderPromotionItems',

                'orderItems.orderItemPersonalizations.personalizationAction',

                'statusChanges.fromStatus',
                'statusChanges.toStatus',
                'statusChanges.changedBy',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => new OrderResource(
                $order,
            ),
        ]);
    }
}
