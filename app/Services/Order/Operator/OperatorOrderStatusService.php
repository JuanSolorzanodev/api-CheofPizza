<?php

declare(strict_types=1);

namespace App\Services\Order\Operator;

use App\Enums\OrderStatusName;
use App\Events\Customer\OrderUpdated as CustomerOrderUpdated;
use App\Events\Operator\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperatorOrderStatusService
{
    public function __construct(
        private readonly OrderStatusTransitionService $transitionService,
        private readonly OperatorOrderQueryService $queryService,
    ) {}

    /**
     * Cambia el estado de un pedido y registra su historial.
     */
    public function changeStatus(
        int $orderId,
        OrderStatusName $destinationStatus,
        ?string $note,
        int $changedByUserId,
    ): Order {
        /**
         * @var array{
         *     order_id: int,
         *     from_status: OrderStatusName,
         *     to_status: OrderStatusName
         * } $result
         */
        $result = DB::transaction(
            function () use (
                $orderId,
                $destinationStatus,
                $note,
                $changedByUserId,
            ): array {
                $order = Order::query()
                    ->with([
                        'deliveryType:id,delivery_type_name',
                        'orderStatus:id,status_name',
                    ])
                    ->lockForUpdate()
                    ->findOrFail(
                        $orderId,
                    );

                $currentStatus = $this->resolveCurrentStatus(
                    $order,
                );

                $deliveryType = trim(
                    (string) $order
                        ->deliveryType
                        ?->delivery_type_name,
                );

                $this->transitionService
                    ->assertCanTransition(
                        currentStatus: $currentStatus,
                        destinationStatus: $destinationStatus,
                        deliveryType: $deliveryType,
                    );

                $destinationStatusModel =
                    $this->findDestinationStatus(
                        $destinationStatus,
                    );

                $fromStatusId = (int) $order->order_status_id;
                $toStatusId = (int) $destinationStatusModel->id;

                $order
                    ->forceFill([
                        'order_status_id' => $toStatusId,
                    ])
                    ->save();

                OrderStatusChange::query()->create([
                    'order_id' => (int) $order->id,
                    'from_order_status_id' => $fromStatusId,
                    'to_order_status_id' => $toStatusId,
                    'changed_by_user_id' => $changedByUserId,
                    'changed_at' => now(),
                    'note' => $note,
                ]);

                return [
                    'order_id' => (int) $order->id,
                    'from_status' => $currentStatus,
                    'to_status' => $destinationStatus,
                ];
            },
            attempts: 3,
        );

        /*
         * La consulta se realiza después de confirmar la transacción,
         * asegurando que los recursos y eventos reciban datos actuales.
         */
        $order = $this->queryService->findOrFail(
            $result['order_id'],
        );

        event(new OrderStatusChanged(
            order: $order,
            fromStatus: $result['from_status']->value,
            toStatus: $result['to_status']->value,
        ));

        event(new CustomerOrderUpdated(
            order: $order,
            action: 'status_changed',
        ));

        return $order;
    }

    /**
     * Resuelve y valida el estado actual de la orden.
     */
    private function resolveCurrentStatus(
        Order $order,
    ): OrderStatusName {
        $statusName = trim(
            (string) $order
                ->orderStatus
                ?->status_name,
        );

        $status = OrderStatusName::tryFrom(
            $statusName,
        );

        if ($status === null) {
            throw ValidationException::withMessages([
                'to_status' => [
                    'La orden no tiene un estado actual válido.',
                ],
            ]);
        }

        return $status;
    }

    /**
     * Recupera el estado de destino desde el catálogo.
     */
    private function findDestinationStatus(
        OrderStatusName $destinationStatus,
    ): OrderStatus {
        $status = OrderStatus::query()
            ->where(
                'status_name',
                $destinationStatus->value,
            )
            ->first();

        if ($status === null) {
            throw ValidationException::withMessages([
                'to_status' => [
                    sprintf(
                        'El estado %s no existe en order_statuses. Ejecuta el seeder de comercio.',
                        $destinationStatus->value,
                    ),
                ],
            ]);
        }

        return $status;
    }
}
