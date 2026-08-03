<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Enums\OrderStatusName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class HourlySalesAnalyticsService
{
    /**
     * Obtiene el comportamiento comercial agrupado por hora.
     *
     * Siempre se devuelven las 24 horas del día, aunque alguna hora
     * no tenga pedidos. Esto evita saltos en las gráficas del frontend.
     *
     * @return array{
     *     period: array<string, mixed>,
     *     summary: array<string, int|float|null>,
     *     hours: list<array<string, int|float>>
     * }
     */
    public function get(
        AnalyticsDateRangeData $range
    ): array {
        $deliveredOrders =
            $this->deliveredOrdersByHour($range);

        $cancelledOrders =
            $this->cancelledOrdersByHour($range);

        $standalonePizzas =
            $this->standalonePizzaUnitsByHour(
                $range
            );

        $promotionPizzas =
            $this->promotionPizzaUnitsByHour(
                $range
            );

        $promotions =
            $this->promotionUnitsByHour($range);

        $hours = $this->buildHourlyTimeline(
            deliveredOrders: $deliveredOrders,
            cancelledOrders: $cancelledOrders,
            standalonePizzas: $standalonePizzas,
            promotionPizzas: $promotionPizzas,
            promotions: $promotions,
        );

        return [
            'period' =>
                $range->toArray(),

            'summary' =>
                $this->calculateSummary($hours),

            'hours' =>
                $hours,
        ];
    }

    /**
     * Pedidos entregados e ingresos agrupados por hora.
     *
     * @return Collection<int, object{
     *     sale_hour: int|string,
     *     delivered_orders: int|string,
     *     gross_sales: int|float|string
     * }>
     */
    private function deliveredOrdersByHour(
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
                HOUR(orders.ordered_at) AS sale_hour,
                COUNT(orders.id) AS delivered_orders,
                COALESCE(
                    SUM(orders.total),
                    0
                ) AS gross_sales
                '
            )
            ->groupByRaw(
                'HOUR(orders.ordered_at)'
            )
            ->orderBy('sale_hour')
            ->get()
            ->keyBy(
                static fn (object $row): int =>
                    (int) $row->sale_hour
            );
    }

    /**
     * Pedidos cancelados agrupados por hora.
     *
     * @return Collection<int, object{
     *     sale_hour: int|string,
     *     cancelled_orders: int|string
     * }>
     */
    private function cancelledOrdersByHour(
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
                HOUR(orders.ordered_at) AS sale_hour,
                COUNT(orders.id) AS cancelled_orders
                '
            )
            ->groupByRaw(
                'HOUR(orders.ordered_at)'
            )
            ->orderBy('sale_hour')
            ->get()
            ->keyBy(
                static fn (object $row): int =>
                    (int) $row->sale_hour
            );
    }

    /**
     * Pizzas individuales y mitad-mitad agrupadas por hora.
     *
     * Una pizza mitad-mitad representa una sola unidad física.
     *
     * @return Collection<int, object{
     *     sale_hour: int|string,
     *     pizzas_sold: int|string
     * }>
     */
    private function standalonePizzaUnitsByHour(
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
                HOUR(orders.ordered_at) AS sale_hour,
                COALESCE(
                    SUM(order_items.quantity),
                    0
                ) AS pizzas_sold
                '
            )
            ->groupByRaw(
                'HOUR(orders.ordered_at)'
            )
            ->orderBy('sale_hour')
            ->get()
            ->keyBy(
                static fn (object $row): int =>
                    (int) $row->sale_hour
            );
    }

    /**
     * Pizzas físicas contenidas dentro de promociones.
     *
     * Cada fila de order_promotion_items representa una pizza del paquete.
     * La cantidad del paquete se conserva en order_items.quantity.
     *
     * @return Collection<int, object{
     *     sale_hour: int|string,
     *     pizzas_sold: int|string
     * }>
     */
    private function promotionPizzaUnitsByHour(
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
                HOUR(orders.ordered_at) AS sale_hour,
                COALESCE(
                    SUM(order_items.quantity),
                    0
                ) AS pizzas_sold
                '
            )
            ->groupByRaw(
                'HOUR(orders.ordered_at)'
            )
            ->orderBy('sale_hour')
            ->get()
            ->keyBy(
                static fn (object $row): int =>
                    (int) $row->sale_hour
            );
    }

    /**
     * Paquetes promocionales vendidos agrupados por hora.
     *
     * @return Collection<int, object{
     *     sale_hour: int|string,
     *     promotions_sold: int|string
     * }>
     */
    private function promotionUnitsByHour(
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
                HOUR(orders.ordered_at) AS sale_hour,
                COALESCE(
                    SUM(order_items.quantity),
                    0
                ) AS promotions_sold
                '
            )
            ->groupByRaw(
                'HOUR(orders.ordered_at)'
            )
            ->orderBy('sale_hour')
            ->get()
            ->keyBy(
                static fn (object $row): int =>
                    (int) $row->sale_hour
            );
    }

    /**
     * Construye las 24 franjas horarias.
     *
     * @param Collection<int, object> $deliveredOrders
     * @param Collection<int, object> $cancelledOrders
     * @param Collection<int, object> $standalonePizzas
     * @param Collection<int, object> $promotionPizzas
     * @param Collection<int, object> $promotions
     *
     * @return list<array{
     *     hour: int,
     *     label: string,
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
    private function buildHourlyTimeline(
        Collection $deliveredOrders,
        Collection $cancelledOrders,
        Collection $standalonePizzas,
        Collection $promotionPizzas,
        Collection $promotions,
    ): array {
        $result = [];

        for ($hour = 0; $hour <= 23; $hour++) {
            $delivered =
                $deliveredOrders->get($hour);

            $cancelled =
                $cancelledOrders->get($hour);

            $standalone =
                $standalonePizzas->get($hour);

            $promotionPizza =
                $promotionPizzas->get($hour);

            $promotion =
                $promotions->get($hour);

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
             * Los reembolsos serán incorporados en el módulo financiero.
             * Se mantiene el campo para conservar un contrato estable.
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
                'hour' =>
                    $hour,

                'label' =>
                    sprintf(
                        '%02d:00',
                        $hour,
                    ),

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
        }

        return $result;
    }

    /**
     * @param list<array<string, int|float|string>> $hours
     *
     * @return array{
     *     gross_sales: float,
     *     net_sales: float,
     *     delivered_orders: int,
     *     cancelled_orders: int,
     *     pizzas_sold: int,
     *     promotions_sold: int,
     *     average_ticket: float,
     *     peak_sales_hour: int|null,
     *     peak_sales_hour_label: string|null,
     *     peak_sales_amount: float,
     *     peak_orders_hour: int|null,
     *     peak_orders_hour_label: string|null,
     *     peak_orders_count: int
     * }
     */
    private function calculateSummary(
        array $hours
    ): array {
        $grossSales = '0.00';
        $netSales = '0.00';

        $deliveredOrders = 0;
        $cancelledOrders = 0;
        $pizzasSold = 0;
        $promotionsSold = 0;

        $peakSalesHour = null;
        $peakSalesAmount = '0.00';

        $peakOrdersHour = null;
        $peakOrdersCount = 0;

        foreach ($hours as $hour) {
            $grossSales = bcadd(
                $grossSales,
                (string) $hour['gross_sales'],
                2,
            );

            $netSales = bcadd(
                $netSales,
                (string) $hour['net_sales'],
                2,
            );

            $deliveredOrders +=
                (int) $hour['delivered_orders'];

            $cancelledOrders +=
                (int) $hour['cancelled_orders'];

            $pizzasSold +=
                (int) $hour['pizzas_sold'];

            $promotionsSold +=
                (int) $hour['promotions_sold'];

            if (
                bccomp(
                    (string) $hour['net_sales'],
                    $peakSalesAmount,
                    2,
                ) === 1
            ) {
                $peakSalesAmount =
                    (string) $hour['net_sales'];

                $peakSalesHour =
                    (int) $hour['hour'];
            }

            if (
                (int) $hour['delivered_orders']
                > $peakOrdersCount
            ) {
                $peakOrdersCount =
                    (int) $hour['delivered_orders'];

                $peakOrdersHour =
                    (int) $hour['hour'];
            }
        }

        $averageTicket =
            $deliveredOrders > 0
                ? bcdiv(
                    $netSales,
                    (string) $deliveredOrders,
                    2,
                )
                : '0.00';

        return [
            'gross_sales' =>
                (float) $grossSales,

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

            'peak_sales_hour' =>
                $peakSalesHour,

            'peak_sales_hour_label' =>
                $peakSalesHour !== null
                    ? sprintf(
                        '%02d:00',
                        $peakSalesHour,
                    )
                    : null,

            'peak_sales_amount' =>
                (float) $peakSalesAmount,

            'peak_orders_hour' =>
                $peakOrdersHour,

            'peak_orders_hour_label' =>
                $peakOrdersHour !== null
                    ? sprintf(
                        '%02d:00',
                        $peakOrdersHour,
                    )
                    : null,

            'peak_orders_count' =>
                $peakOrdersCount,
        ];
    }
}
