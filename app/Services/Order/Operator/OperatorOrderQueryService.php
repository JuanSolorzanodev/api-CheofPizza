<?php

declare(strict_types=1);

namespace App\Services\Order\Operator;

use App\Enums\OrderStatusName;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

final class OperatorOrderQueryService
{
    /**
     * Recupera los pedidos visibles en el panel del operador.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Order>
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

        $orders = $query->paginate(
            $perPage,
        );

        /*
         * Los recursos de listado requieren los ítems y promociones
         * para calcular correctamente el resumen de cada pedido.
         */
        $orders
            ->getCollection()
            ->load([
                'orderItems',
                'orderItems.orderPromotionItems',
            ]);

        return $orders;
    }

    /**
     * Recupera el detalle completo de un pedido.
     */
    public function findOrFail(
        int $orderId,
    ): Order {
        return Order::query()
            ->with([
                'orderStatus:id,status_name',
                'deliveryType:id,delivery_type_name',
                'paymentMethod:id,name',
                'latestPaymentReceipt.reviewer:id,first_name,last_name',
                'user:id,first_name,last_name,email,phone',

                'orderItems' => static function (
                    HasMany $query,
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
            ->findOrFail(
                $orderId,
            );
    }

    /**
     * Devuelve la cantidad de pedidos agrupada por estado.
     *
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
     * Devuelve todos los estados reconocidos por el sistema.
     *
     * @return list<string>
     */
    public function allStatuses(): array
    {
        return OrderStatusName::values();
    }

    /**
     * @param  Builder<Order>  $query
     * @param  array<string, mixed>  $filters
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

        if (! empty($filters['date_from'])) {
            $query->whereDate(
                'ordered_at',
                '>=',
                $filters['date_from'],
            );
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate(
                'ordered_at',
                '<=',
                $filters['date_to'],
            );
        }
    }
}
