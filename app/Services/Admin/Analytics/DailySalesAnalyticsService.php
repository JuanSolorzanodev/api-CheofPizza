<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Enums\OrderStatusName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DailySalesAnalyticsService
{
    /**
     * Obtiene las ventas agrupadas por día.
     *
     * Se incluyen también los días sin actividad para que el frontend
     * pueda construir gráficas continuas y comparaciones correctas.
     *
     * @return array{
     *     period: array<string, mixed>,
     *     totals: array<string, int|float>,
     *     days: list<array<string, int|float>>
     * }
     */
    public function get(
        AnalyticsDateRangeData $range
    ): array {
        $deliveredOrders =
            $this->deliveredOrdersByDay($range);

        $cancelledOrders =
            $this->cancelledOrdersByDay($range);

        $standalonePizzas =
            $this->standalonePizzaUnitsByDay($range);

        $promotionPizzas =
            $this->promotionPizzaUnitsByDay($range);

        $promotions =
            $this->promotionUnitsByDay($range);

        $days = $this->buildCalendar(
            range: $range,
            deliveredOrders: $deliveredOrders,
            cancelledOrders: $cancelledOrders,
            standalonePizzas: $standalonePizzas,
            promotionPizzas: $promotionPizzas,
            promotions: $promotions,
        );

        return [
            'period' =>
                $range->toArray(),

            'totals' =>
                $this->calculateTotals($days),

            'days' =>
                $days,
        ];
    }

    /**
     * Ventas y pedidos entregados agrupados por fecha.
     *
     * @return Collection<string, object{
     *     sale_date: string,
     *     delivered_orders: int|string,
     *     gross_sales: int|float|string
     * }>
     */
    private function deliveredOrdersByDay(
        AnalyticsDateRangeData $range
    ): Collection {
        return DB::table('orders')
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'orders.order_status_id',
            )
            ->where(
                'order_statuses.status_name',
                OrderStatusName::Delivered->value,
            )
            ->whereBetween(
                'orders.ordered_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                DATE(orders.ordered_at) AS sale_date,
                COUNT(orders.id) AS delivered_orders,
                COALESCE(SUM(orders.total), 0) AS gross_sales
                '
            )
            ->groupByRaw(
                'DATE(orders.ordered_at)'
            )
            ->orderBy('sale_date')
            ->get()
            ->keyBy('sale_date');
    }

    /**
     * Pedidos cancelados agrupados por fecha.
     *
     * @return Collection<string, object{
     *     sale_date: string,
     *     cancelled_orders: int|string
     * }>
     */
    private function cancelledOrdersByDay(
        AnalyticsDateRangeData $range
    ): Collection {
        return DB::table('orders')
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'orders.order_status_id',
            )
            ->where(
                'order_statuses.status_name',
                OrderStatusName::Cancelled->value,
            )
            ->whereBetween(
                'orders.ordered_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                DATE(orders.ordered_at) AS sale_date,
                COUNT(orders.id) AS cancelled_orders
                '
            )
            ->groupByRaw(
                'DATE(orders.ordered_at)'
            )
            ->orderBy('sale_date')
            ->get()
            ->keyBy('sale_date');
    }

    /**
     * Pizzas individuales y mitad-mitad.
     *
     * Una pizza mitad-mitad continúa representando una sola unidad física.
     *
     * @return Collection<string, object{
     *     sale_date: string,
     *     pizzas_sold: int|string
     * }>
     */
    private function standalonePizzaUnitsByDay(
        AnalyticsDateRangeData $range
    ): Collection {
        return DB::table('orders')
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'orders.order_status_id',
            )
            ->join(
                'order_items',
                'order_items.order_id',
                '=',
                'orders.id',
            )
            ->where(
                'order_statuses.status_name',
                OrderStatusName::Delivered->value,
            )
            ->whereBetween(
                'orders.ordered_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->whereNull(
                'order_items.promotion_id'
            )
            ->whereNotNull(
                'order_items.pizza_id'
            )
            ->selectRaw(
                '
                DATE(orders.ordered_at) AS sale_date,
                COALESCE(
                    SUM(order_items.quantity),
                    0
                ) AS pizzas_sold
                '
            )
            ->groupByRaw(
                'DATE(orders.ordered_at)'
            )
            ->orderBy('sale_date')
            ->get()
            ->keyBy('sale_date');
    }

    /**
     * Pizzas físicas incluidas dentro de promociones.
     *
     * Cada fila de order_promotion_items representa una pizza del paquete.
     * Se multiplica implícitamente por la cantidad del OrderItem al sumar
     * order_items.quantity por cada fila relacionada.
     *
     * @return Collection<string, object{
     *     sale_date: string,
     *     pizzas_sold: int|string
     * }>
     */
    private function promotionPizzaUnitsByDay(
        AnalyticsDateRangeData $range
    ): Collection {
        return DB::table('orders')
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'orders.order_status_id',
            )
            ->join(
                'order_items',
                'order_items.order_id',
                '=',
                'orders.id',
            )
            ->join(
                'order_promotion_items',
                'order_promotion_items.order_item_id',
                '=',
                'order_items.id',
            )
            ->where(
                'order_statuses.status_name',
                OrderStatusName::Delivered->value,
            )
            ->whereBetween(
                'orders.ordered_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->whereNotNull(
                'order_items.promotion_id'
            )
            ->selectRaw(
                '
                DATE(orders.ordered_at) AS sale_date,
                COALESCE(
                    SUM(order_items.quantity),
                    0
                ) AS pizzas_sold
                '
            )
            ->groupByRaw(
                'DATE(orders.ordered_at)'
            )
            ->orderBy('sale_date')
            ->get()
            ->keyBy('sale_date');
    }

    /**
     * Cantidad de paquetes promocionales vendidos.
     *
     * @return Collection<string, object{
     *     sale_date: string,
     *     promotions_sold: int|string
     * }>
     */
    private function promotionUnitsByDay(
        AnalyticsDateRangeData $range
    ): Collection {
        return DB::table('orders')
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'orders.order_status_id',
            )
            ->join(
                'order_items',
                'order_items.order_id',
                '=',
                'orders.id',
            )
            ->where(
                'order_statuses.status_name',
                OrderStatusName::Delivered->value,
            )
            ->whereBetween(
                'orders.ordered_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->whereNotNull(
                'order_items.promotion_id'
            )
            ->selectRaw(
                '
                DATE(orders.ordered_at) AS sale_date,
                COALESCE(
                    SUM(order_items.quantity),
                    0
                ) AS promotions_sold
                '
            )
            ->groupByRaw(
                'DATE(orders.ordered_at)'
            )
            ->orderBy('sale_date')
            ->get()
            ->keyBy('sale_date');
    }

    /**
     * Construye una fila por cada día calendario del periodo.
     *
     * @param Collection<string, object> $deliveredOrders
     * @param Collection<string, object> $cancelledOrders
     * @param Collection<string, object> $standalonePizzas
     * @param Collection<string, object> $promotionPizzas
     * @param Collection<string, object> $promotions
     *
     * @return list<array{
     *     date: string,
     *     gross_sales: float,
     *     refunds: float,
     *     net_sales: float,
     *     delivered_orders: int,
     *     cancelled_orders: int,
     *     pizzas_sold: int,
     *     promotions_sold: int,
     *     average_ticket: float,
     *     cancellation_rate: float
     * }>
     */
    private function buildCalendar(
        AnalyticsDateRangeData $range,
        Collection $deliveredOrders,
        Collection $cancelledOrders,
        Collection $standalonePizzas,
        Collection $promotionPizzas,
        Collection $promotions,
    ): array {
        $result = [];

        $date = $range->from->startOfDay();
        $lastDate = $range->to->startOfDay();

        while ($date->lessThanOrEqualTo($lastDate)) {
            $dateKey = $date->toDateString();

            $delivered =
                $deliveredOrders->get($dateKey);

            $cancelled =
                $cancelledOrders->get($dateKey);

            $standalone =
                $standalonePizzas->get($dateKey);

            $promotionPizza =
                $promotionPizzas->get($dateKey);

            $promotion =
                $promotions->get($dateKey);

            $grossSales = (string) (
                $delivered->gross_sales
                ?? '0.00'
            );

            $deliveredCount = (int) (
                $delivered->delivered_orders
                ?? 0
            );

            $cancelledCount = (int) (
                $cancelled->cancelled_orders
                ?? 0
            );

            $pizzasSold =
                (int) (
                    $standalone->pizzas_sold
                    ?? 0
                )
                + (int) (
                    $promotionPizza->pizzas_sold
                    ?? 0
                );

            $promotionsSold = (int) (
                $promotion->promotions_sold
                ?? 0
            );

            /*
             * Los reembolsos se implementarán en EP-ADMIN-02.
             * No se inventa un importe que todavía no está persistido
             * de forma auditable en el modelo transaccional.
             */
            $refunds = '0.00';

            $netSales = bcsub(
                $grossSales,
                $refunds,
                2,
            );

            $averageTicket =
                $deliveredCount > 0
                    ? bcdiv(
                        $netSales,
                        (string) $deliveredCount,
                        2,
                    )
                    : '0.00';

            $finishedOrders =
                $deliveredCount
                + $cancelledCount;

            $cancellationRate =
                $finishedOrders > 0
                    ? round(
                        (
                            $cancelledCount
                            / $finishedOrders
                        ) * 100,
                        2,
                    )
                    : 0.0;

            $result[] = [
                'date' =>
                    $dateKey,

                'gross_sales' =>
                    (float) $grossSales,

                'refunds' =>
                    (float) $refunds,

                'net_sales' =>
                    (float) $netSales,

                'delivered_orders' =>
                    $deliveredCount,

                'cancelled_orders' =>
                    $cancelledCount,

                'pizzas_sold' =>
                    $pizzasSold,

                'promotions_sold' =>
                    $promotionsSold,

                'average_ticket' =>
                    (float) $averageTicket,

                'cancellation_rate' =>
                    $cancellationRate,
            ];

            $date = $date->addDay();
        }

        return $result;
    }

    /**
     * @param list<array<string, int|float>> $days
     *
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
    private function calculateTotals(
        array $days
    ): array {
        $grossSales = '0.00';
        $refunds = '0.00';
        $netSales = '0.00';

        $deliveredOrders = 0;
        $cancelledOrders = 0;
        $pizzasSold = 0;
        $promotionsSold = 0;

        foreach ($days as $day) {
            $grossSales = bcadd(
                $grossSales,
                (string) $day['gross_sales'],
                2,
            );

            $refunds = bcadd(
                $refunds,
                (string) $day['refunds'],
                2,
            );

            $netSales = bcadd(
                $netSales,
                (string) $day['net_sales'],
                2,
            );

            $deliveredOrders +=
                (int) $day['delivered_orders'];

            $cancelledOrders +=
                (int) $day['cancelled_orders'];

            $pizzasSold +=
                (int) $day['pizzas_sold'];

            $promotionsSold +=
                (int) $day['promotions_sold'];
        }

        $averageTicket =
            $deliveredOrders > 0
                ? bcdiv(
                    $netSales,
                    (string) $deliveredOrders,
                    2,
                )
                : '0.00';

        $finishedOrders =
            $deliveredOrders
            + $cancelledOrders;

        $cancellationRate =
            $finishedOrders > 0
                ? round(
                    (
                        $cancelledOrders
                        / $finishedOrders
                    ) * 100,
                    2,
                )
                : 0.0;

        return [
            'gross_sales' =>
                (float) $grossSales,

            'refunds' =>
                (float) $refunds,

            'net_sales' =>
                (float) $netSales,

            'delivered_orders' =>
                $deliveredOrders,

            'cancelled_orders' =>
                $cancelledOrders,

            'pizzas_sold' =>
                $pizzasSold,

            'promotions_sold' =>
                $promotionsSold,

            'average_ticket' =>
                (float) $averageTicket,

            'cancellation_rate' =>
                $cancellationRate,
        ];
    }
}
