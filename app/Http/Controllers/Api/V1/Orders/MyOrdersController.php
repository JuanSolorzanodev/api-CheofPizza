<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Resources\Api\V1\BankAccountPublicResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Services\Payments\TransferAccountService;
use Illuminate\Http\Request;

class MyOrdersController
{
    public function __construct(
        private readonly TransferAccountService $transferAccountService,
    ) {}

    /**
     * GET /api/v1/my/orders
     * Lista paginada de órdenes del usuario autenticado (ORM: relación user->orders)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = $user->orders()
            ->with(['deliveryType', 'paymentMethod', 'orderStatus'])
            ->latest('ordered_at')
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    /**
     * GET /api/v1/my/orders/{orderId}
     * Detalle de una orden del usuario autenticado (ORM: relación user->orders)
     */
    public function show(Request $request, int $orderId)
    {
        $user = $request->user();

        /** @var Order $order */
        $order = $user->orders()
            ->with([
                'deliveryType',
                'paymentMethod',
                'orderStatus',
                'orderItems.orderItemPersonalizations',
                'statusChanges.fromStatus',
                'statusChanges.toStatus',
            ])
            ->findOrFail($orderId);

        $data = (new OrderResource($order))->toArray($request);

        // Si es transferencia: devolvemos cuenta activa (para pagar desde el detalle)
        if (($order->paymentMethod?->name ?? null) === 'transfer') {
            $account = $this->transferAccountService->getActivePrimary();

            $data['transfer_account'] = $account
                ? (new BankAccountPublicResource($account))->toArray($request)
                : null;

            $data['payment_hint'] = 'Realiza la transferencia y envía el comprobante por WhatsApp para validar tu pedido.';
        } else {
            $data['transfer_account'] = null;
            $data['payment_hint'] = null;
        }

        return response()->json(['data' => $data]);
    }
}
