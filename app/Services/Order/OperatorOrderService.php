<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatusName;
use App\Events\Customer\OrderUpdated as CustomerOrderUpdated;
use App\Events\Operator\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperatorOrderService
{
    public function __construct(
        private readonly OrderStatusTransitionService $transitionService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(
        array $filters,
    ): LengthAwarePaginator {
        $perPage = max(
            1,
            min(
                100,
                (int) ($filters['per_page'] ?? 15),
            ),
        );

        $query = Order::query()
            ->with([
                'user',
                'deliveryType',
                'paymentMethod',
                'orderStatus',
            ])
            ->latest('ordered_at');

        $this->applyFilters(
            query: $query,
            filters: $filters,
        );

        return $query->paginate($perPage);
    }

    public function findOrFail(
        int $orderId,
    ): Order {
        return Order::query()
            ->with([
                'orderStatus:id,status_name',
                'deliveryType:id,delivery_type_name',
                'paymentMethod:id,name',
                'user:id,first_name,last_name,email,phone',

                'orderItems' => static function (
                    $query,
                ): void {
                    $query->select([
                        'id',
                        'order_id',
                        'promotion_id',
                        'promotion_name',
                        'pizza_id',
                        'pizza_name',
                        'pizza_id_second',
                        'pizza_name_second',
                        'size_id',
                        'size_name',
                        'category_name',
                        'category_name_second',
                        'is_half_and_half',
                        'quantity',
                        'unit_price',
                        'subtotal',
                    ]);
                },

                'orderItems.pizza:id,pizza_name,description',
                'orderItems.pizza.ingredients:id,ingredient_name',

                'orderItems.pizza.pizzaIngredients:id,pizza_id,ingredient_id',
                'orderItems.pizza.pizzaIngredients.ingredient:id,ingredient_name',

                'orderItems.pizzaSecond:id,pizza_name,description',
                'orderItems.pizzaSecond.ingredients:id,ingredient_name',

                'orderItems.pizzaSecond.pizzaIngredients:id,pizza_id,ingredient_id',
                'orderItems.pizzaSecond.pizzaIngredients.ingredient:id,ingredient_name',

                'orderItems.orderPromotionItems:id,order_item_id,pizza_id,pizza_name',
                'orderItems.orderPromotionItems.pizza:id,pizza_name,description',
                'orderItems.orderPromotionItems.pizza.ingredients:id,ingredient_name',

                'orderItems.orderPromotionItems.pizza.pizzaIngredients:id,pizza_id,ingredient_id',
                'orderItems.orderPromotionItems.pizza.pizzaIngredients.ingredient:id,ingredient_name',

                'orderItems.orderItemPersonalizations:id,order_item_id,ingredient_id,ingredient_name,personalization_action_id,applies_to,modification_type,extra_price',
                'orderItems.orderItemPersonalizations.personalizationAction:id,action_name',

                'statusChanges.fromStatus:id,status_name',
                'statusChanges.toStatus:id,status_name',
                'statusChanges.changedBy:id,first_name,last_name,email',
            ])
            ->findOrFail($orderId);
    }

    public function changeStatus(
        int $orderId,
        OrderStatusName $destinationStatus,
        ?string $note,
        int $changedByUserId,
    ): Order {
        /**
         * @var array{
         *     order: Order,
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
                /** @var Order $order */
                $order = Order::query()
                    ->with([
                        'deliveryType:id,delivery_type_name',
                        'orderStatus:id,status_name',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($orderId);

                $currentStatusName = trim(
                    (string) $order->orderStatus?->status_name,
                );

                $currentStatus = OrderStatusName::tryFrom(
                    $currentStatusName,
                );

                if ($currentStatus === null) {
                    throw ValidationException::withMessages([
                        'to_status' => [
                            'La orden no tiene un estado actual válido.',
                        ],
                    ]);
                }

                $deliveryType = trim(
                    (string) $order
                        ->deliveryType
                        ?->delivery_type_name,
                );

                $this->transitionService->assertCanTransition(
                    currentStatus: $currentStatus,
                    destinationStatus: $destinationStatus,
                    deliveryType: $deliveryType,
                );

                $destinationStatusModel = OrderStatus::query()
                    ->where(
                        'status_name',
                        $destinationStatus->value,
                    )
                    ->first();

                if ($destinationStatusModel === null) {
                    throw ValidationException::withMessages([
                        'to_status' => [
                            sprintf(
                                'El estado %s no existe en order_statuses. Ejecuta el seeder de comercio.',
                                $destinationStatus->value,
                            ),
                        ],
                    ]);
                }

                $fromStatusId = (int) $order->order_status_id;
                $toStatusId = (int) $destinationStatusModel->id;

                $order->forceFill([
                    'order_status_id' => $toStatusId,
                ])->save();

                OrderStatusChange::query()->create([
                    'order_id' => (int) $order->id,
                    'from_order_status_id' => $fromStatusId,
                    'to_order_status_id' => $toStatusId,
                    'changed_by_user_id' => $changedByUserId,
                    'changed_at' => now(),
                    'note' => $note,
                ]);

                return [
                    'order' => $order,
                    'from_status' => $currentStatus,
                    'to_status' => $destinationStatus,
                ];
            },
            attempts: 3,
        );

        /*
         * La transacción ya confirmó el cambio.
         * Los eventos además implementan ShouldDispatchAfterCommit.
         */
        $freshOrder = $this->findOrFail(
            (int) $result['order']->id,
        );

        event(new OrderStatusChanged(
            order: $freshOrder,
            fromStatus: $result['from_status']->value,
            toStatus: $result['to_status']->value,
        ));

        event(new CustomerOrderUpdated(
            order: $freshOrder,
            action: 'status_changed',
        ));

        return $freshOrder;
    }

    /**
     * @return array<string, int>
     */
    public function queueCounts(): array
    {
        $rows = OrderStatus::query()
            ->select([
                'id',
                'status_name',
            ])
            ->withCount([
                'orders as orders_count',
            ])
            ->orderBy('id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->status_name] =
                (int) $row->orders_count;
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    public function allStatuses(): array
    {
        return OrderStatusName::values();
    }

    /**
     * @param Builder<Order> $query
     * @param array<string, mixed> $filters
     */
    private function applyFilters(
        Builder $query,
        array $filters,
    ): void {
        $term = trim(
            (string) ($filters['q'] ?? ''),
        );

        if ($term !== '') {
            $query->where(
                static function (
                    Builder $nestedQuery,
                ) use ($term): void {
                    $nestedQuery
                        ->where(
                            'order_number',
                            'like',
                            "%{$term}%",
                        )
                        ->orWhere(
                            'address',
                            'like',
                            "%{$term}%",
                        );
                },
            );
        }

        $status = trim(
            (string) ($filters['status'] ?? ''),
        );

        if ($status !== '') {
            $query->whereHas(
                'orderStatus',
                static fn (
                    Builder $statusQuery,
                ): Builder => $statusQuery->where(
                    'status_name',
                    $status,
                ),
            );
        }

        $deliveryType = trim(
            (string) ($filters['delivery_type'] ?? ''),
        );

        if ($deliveryType !== '') {
            $query->whereHas(
                'deliveryType',
                static fn (
                    Builder $deliveryQuery,
                ): Builder => $deliveryQuery->where(
                    'delivery_type_name',
                    $deliveryType,
                ),
            );
        }

        $paymentMethod = trim(
            (string) ($filters['payment_method'] ?? ''),
        );

        if ($paymentMethod !== '') {
            $query->whereHas(
                'paymentMethod',
                static fn (
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->where(
                    'name',
                    $paymentMethod,
                ),
            );
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate(
                'ordered_at',
                '>=',
                $filters['date_from'],
            );
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate(
                'ordered_at',
                '<=',
                $filters['date_to'],
            );
        }
    }
}
