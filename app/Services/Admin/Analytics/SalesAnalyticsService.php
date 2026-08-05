<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Enums\OrderStatusName;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class SalesAnalyticsService
{
    /**
     * Obtiene el resumen comercial del periodo solicitado y lo compara
     * contra el periodo inmediatamente anterior con la misma duración.
     *
     * La fuente de verdad son las tablas transaccionales:
     *
     * - orders
     * - order_items
     * - order_promotion_items
     * - order_statuses
     *
     * Los agregados históricos se implementarán posteriormente como una
     * optimización idempotente, pero nunca sustituirán a estas tablas.
     *
     * @return array<string, mixed>
     */
    public function dashboard(
        AnalyticsDateRangeData $range
    ): array {
        $current = $this->metricsForRange(
            $range
        );

        $previousRange = $range->previous();

        $previous = $this->metricsForRange(
            $previousRange
        );

        return [
            'period' => $range->toArray(),

            'summary' => $current,

            'comparison' => [
                'period' => $previousRange->toArray(),

                'net_sales_percentage' => $this->percentageChange(
                    current: $current['net_sales'],
                    previous: $previous['net_sales'],
                ),

                'delivered_orders_percentage' => $this->percentageChange(
                    current: $current['delivered_orders'],
                    previous: $previous['delivered_orders'],
                ),

                'pizzas_sold_percentage' => $this->percentageChange(
                    current: $current['pizzas_sold'],
                    previous: $previous['pizzas_sold'],
                ),

                'average_ticket_percentage' => $this->percentageChange(
                    current: $current['average_ticket'],
                    previous: $previous['average_ticket'],
                ),
            ],

            'previous_summary' => $previous,
        ];
    }

    /**
     * @return array{
     *     gross_sales: float,
     *     refunds: float,
     *     net_sales: float,
     *     delivered_orders: int,
     *     cancelled_orders: int,
     *     pizzas_sold: int,
     *     promotions_sold: int,
     *     average_ticket: float,
     *     cancellation_rate: float
     * }
     */
    private function metricsForRange(
        AnalyticsDateRangeData $range
    ): array {
        $deliveredOrdersQuery =
            $this->ordersForRange(
                range: $range,
                status: OrderStatusName::Delivered,
            );

        $cancelledOrdersQuery =
            $this->ordersForRange(
                range: $range,
                status: OrderStatusName::Cancelled,
            );

        $deliveredOrderIds = (
            clone $deliveredOrdersQuery
        )->select('orders.id');

        $deliveredOrders = (
            clone $deliveredOrdersQuery
        )->count();

        $cancelledOrders = (
            clone $cancelledOrdersQuery
        )->count();

        /*
         * Se utiliza el total guardado en cada pedido como snapshot
         * financiero. No se recalcula con precios actuales del catálogo.
         */
        $grossSales = (string) (
            clone $deliveredOrdersQuery
        )->sum('orders.total');

        $standalonePizzas =
            $this->standalonePizzaUnits(
                $deliveredOrderIds
            );

        $promotionPizzas =
            $this->promotionPizzaUnits(
                $deliveredOrderIds
            );

        $promotionsSold =
            $this->promotionUnits(
                $deliveredOrderIds
            );

        $pizzasSold =
            $standalonePizzas
            + $promotionPizzas;

        /*
         * Los reembolsos se incorporarán en EP-ADMIN-02.
         *
         * Por el momento se devuelve explícitamente cero y no se intenta
         * inferir un monto desde refunded_at, porque la tabla payments no
         * conserva todavía el importe reembolsado parcial o total de forma
         * auditable.
         */
        $refunds = '0.00';

        $netSales = bcsub(
            $grossSales,
            $refunds,
            2,
        );

        $averageTicket = $deliveredOrders > 0
            ? bcdiv(
                $netSales,
                (string) $deliveredOrders,
                2,
            )
            : '0.00';

        $completedOrCancelled =
            $deliveredOrders
            + $cancelledOrders;

        $cancellationRate =
            $completedOrCancelled > 0
            ? round(
                (
                    $cancelledOrders
                    / $completedOrCancelled
                ) * 100,
                2,
            )
            : 0.0;

        return [
            'gross_sales' => (float) $grossSales,

            'refunds' => (float) $refunds,

            'net_sales' => (float) $netSales,

            'delivered_orders' => $deliveredOrders,

            'cancelled_orders' => $cancelledOrders,

            'pizzas_sold' => $pizzasSold,

            'promotions_sold' => $promotionsSold,

            'average_ticket' => (float) $averageTicket,

            'cancellation_rate' => $cancellationRate,
        ];
    }

    private function ordersForRange(
        AnalyticsDateRangeData $range,
        OrderStatusName $status,
    ): Builder {
        return Order::query()
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'orders.order_status_id',
            )
            ->where(
                'order_statuses.status_name',
                $status->value,
            )
            ->whereBetween(
                'orders.ordered_at',
                [
                    $range->from,
                    $range->to,
                ],
            );
    }

    /**
     * Cuenta pizzas individuales.
     *
     * Una pizza mitad y mitad sigue siendo una sola unidad física,
     * por lo que su quantity se contabiliza una sola vez.
     */
    private function standalonePizzaUnits(
        Builder $deliveredOrderIds
    ): int {
        return (int) DB::table(
            'order_items'
        )
            ->whereIn(
                'order_id',
                $deliveredOrderIds,
            )
            ->whereNull(
                'promotion_id'
            )
            ->whereNotNull(
                'pizza_id'
            )
            ->sum(
                'quantity'
            );
    }

    /**
     * Cuenta las pizzas físicas contenidas dentro de promociones.
     *
     * Ejemplo:
     * - promoción con dos pizzas;
     * - quantity del order_item = 3;
     * - existen dos filas en order_promotion_items.
     *
     * Resultado: 2 × 3 = 6 pizzas físicas.
     */
    private function promotionPizzaUnits(
        Builder $deliveredOrderIds
    ): int {
        $value = DB::table(
            'order_promotion_items'
        )
            ->join(
                'order_items',
                'order_items.id',
                '=',
                'order_promotion_items.order_item_id',
            )
            ->whereIn(
                'order_items.order_id',
                $deliveredOrderIds,
            )
            ->whereNotNull(
                'order_items.promotion_id'
            )
            ->selectRaw(
                '
                COALESCE(
                    SUM(order_items.quantity),
                    0
                ) AS total_units
                '
            )
            ->value(
                'total_units'
            );

        return (int) ($value ?? 0);
    }

    /**
     * Cuenta cuántas promociones comerciales se vendieron.
     *
     * No cuenta las pizzas contenidas, sino la cantidad de paquetes
     * promocionales comprados.
     */
    private function promotionUnits(
        Builder $deliveredOrderIds
    ): int {
        return (int) DB::table(
            'order_items'
        )
            ->whereIn(
                'order_id',
                $deliveredOrderIds,
            )
            ->whereNotNull(
                'promotion_id'
            )
            ->sum(
                'quantity'
            );
    }

    private function percentageChange(
        int|float $current,
        int|float $previous,
    ): ?float {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0
                ? 0.0
                : null;
        }

        return round(
            (
                (
                    (float) $current
                    - (float) $previous
                )
                / abs(
                    (float) $previous
                )
            ) * 100,
            2,
        );
    }
}
