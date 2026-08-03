<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Enums\OrderStatusName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ProductPerformanceAnalyticsService
{
    /**
     * Obtiene el rendimiento comercial de pizzas, promociones y tamaños.
     *
     * Reglas de contabilización:
     *
     * - Pizza completa: cada unidad cuenta como 1.
     * - Mitad y mitad: cada sabor recibe 0.5 por pizza física.
     * - Promoción: cada fila de order_promotion_items representa una pizza
     *   dentro del paquete y se multiplica por order_items.quantity.
     *
     * @return array{
     *     period: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     pizzas: list<array<string, int|float|string|null>>,
     *     promotions: list<array<string, int|float|string|null>>,
     *     sizes: list<array<string, int|float|string|null>>
     * }
     */
    public function get(
        AnalyticsDateRangeData $range
    ): array {
        $pizzas = $this->pizzaPerformance($range);
        $promotions = $this->promotionPerformance(
            $range
        );
        $sizes = $this->sizePerformance($range);

        return [
            'period' =>
                $range->toArray(),

            'summary' =>
                $this->buildSummary(
                    pizzas: $pizzas,
                    promotions: $promotions,
                    sizes: $sizes,
                ),

            'pizzas' =>
                $pizzas,

            'promotions' =>
                $promotions,

            'sizes' =>
                $sizes,
        ];
    }

    /**
     * Ranking consolidado de pizzas.
     *
     * @return list<array{
     *     pizza_id: int|null,
     *     pizza_name: string,
     *     equivalent_units: float,
     *     complete_units: int,
     *     half_units: float,
     *     promotion_units: int
     * }>
     */
    private function pizzaPerformance(
        AnalyticsDateRangeData $range
    ): array {
        $completePizzas =
            $this->deliveredOrderItemsBase($range)
                ->whereNull(
                    'order_items.promotion_id'
                )
                ->where(
                    'order_items.is_half_and_half',
                    false,
                )
                ->whereNotNull(
                    'order_items.pizza_id'
                )
                ->selectRaw(
                    '
                    order_items.pizza_id AS pizza_id,
                    COALESCE(
                        order_items.pizza_name,
                        "Pizza sin nombre"
                    ) AS pizza_name,
                    SUM(order_items.quantity) AS equivalent_units,
                    SUM(order_items.quantity) AS complete_units,
                    0 AS half_units,
                    0 AS promotion_units
                    '
                )
                ->groupBy(
                    'order_items.pizza_id',
                    'order_items.pizza_name',
                );

        $firstHalfPizzas =
            $this->deliveredOrderItemsBase($range)
                ->whereNull(
                    'order_items.promotion_id'
                )
                ->where(
                    'order_items.is_half_and_half',
                    true,
                )
                ->whereNotNull(
                    'order_items.pizza_id'
                )
                ->selectRaw(
                    '
                    order_items.pizza_id AS pizza_id,
                    COALESCE(
                        order_items.pizza_name,
                        "Pizza sin nombre"
                    ) AS pizza_name,
                    SUM(order_items.quantity * 0.5) AS equivalent_units,
                    0 AS complete_units,
                    SUM(order_items.quantity * 0.5) AS half_units,
                    0 AS promotion_units
                    '
                )
                ->groupBy(
                    'order_items.pizza_id',
                    'order_items.pizza_name',
                );

        $secondHalfPizzas =
            $this->deliveredOrderItemsBase($range)
                ->whereNull(
                    'order_items.promotion_id'
                )
                ->where(
                    'order_items.is_half_and_half',
                    true,
                )
                ->whereNotNull(
                    'order_items.pizza_id_second'
                )
                ->selectRaw(
                    '
                    order_items.pizza_id_second AS pizza_id,
                    COALESCE(
                        order_items.pizza_name_second,
                        "Pizza sin nombre"
                    ) AS pizza_name,
                    SUM(order_items.quantity * 0.5) AS equivalent_units,
                    0 AS complete_units,
                    SUM(order_items.quantity * 0.5) AS half_units,
                    0 AS promotion_units
                    '
                )
                ->groupBy(
                    'order_items.pizza_id_second',
                    'order_items.pizza_name_second',
                );

        $promotionPizzas =
            $this->deliveredOrderItemsBase($range)
                ->join(
                    'order_promotion_items',
                    'order_promotion_items.order_item_id',
                    '=',
                    'order_items.id',
                )
                ->whereNotNull(
                    'order_items.promotion_id'
                )
                ->selectRaw(
                    '
                    order_promotion_items.pizza_id AS pizza_id,
                    COALESCE(
                        order_promotion_items.pizza_name,
                        "Pizza sin nombre"
                    ) AS pizza_name,
                    SUM(order_items.quantity) AS equivalent_units,
                    0 AS complete_units,
                    0 AS half_units,
                    SUM(order_items.quantity) AS promotion_units
                    '
                )
                ->groupBy(
                    'order_promotion_items.pizza_id',
                    'order_promotion_items.pizza_name',
                );

        $union = $completePizzas
            ->unionAll($firstHalfPizzas)
            ->unionAll($secondHalfPizzas)
            ->unionAll($promotionPizzas);

        return DB::query()
            ->fromSub(
                $union,
                'pizza_performance',
            )
            ->selectRaw(
                '
                pizza_id,
                pizza_name,
                SUM(equivalent_units) AS equivalent_units,
                SUM(complete_units) AS complete_units,
                SUM(half_units) AS half_units,
                SUM(promotion_units) AS promotion_units
                '
            )
            ->groupBy(
                'pizza_id',
                'pizza_name',
            )
            ->orderByDesc(
                'equivalent_units'
            )
            ->orderBy(
                'pizza_name'
            )
            ->get()
            ->map(
                static function (
                    object $row
                ): array {
                    return [
                        'pizza_id' =>
                            $row->pizza_id !== null
                                ? (int) $row->pizza_id
                                : null,

                        'pizza_name' =>
                            (string) $row->pizza_name,

                        'equivalent_units' =>
                            round(
                                (float) $row->equivalent_units,
                                2,
                            ),

                        'complete_units' =>
                            (int) $row->complete_units,

                        'half_units' =>
                            round(
                                (float) $row->half_units,
                                2,
                            ),

                        'promotion_units' =>
                            (int) $row->promotion_units,
                    ];
                },
            )
            ->values()
            ->all();
    }

    /**
     * Rendimiento de paquetes promocionales.
     *
     * El ingreso corresponde al subtotal histórico almacenado en el
     * OrderItem y no al precio actual de la promoción.
     *
     * @return list<array{
     *     promotion_id: int|null,
     *     promotion_name: string,
     *     packages_sold: int,
     *     gross_sales: float
     * }>
     */
    private function promotionPerformance(
        AnalyticsDateRangeData $range
    ): array {
        return $this->deliveredOrderItemsBase(
            $range
        )
            ->whereNotNull(
                'order_items.promotion_id'
            )
            ->selectRaw(
                '
                order_items.promotion_id,
                COALESCE(
                    order_items.promotion_name,
                    "Promoción sin nombre"
                ) AS promotion_name,
                SUM(order_items.quantity) AS packages_sold,
                COALESCE(
                    SUM(order_items.subtotal),
                    0
                ) AS gross_sales
                '
            )
            ->groupBy(
                'order_items.promotion_id',
                'order_items.promotion_name',
            )
            ->orderByDesc(
                'packages_sold'
            )
            ->orderByDesc(
                'gross_sales'
            )
            ->orderBy(
                'promotion_name'
            )
            ->get()
            ->map(
                static function (
                    object $row
                ): array {
                    return [
                        'promotion_id' =>
                            $row->promotion_id !== null
                                ? (int) $row->promotion_id
                                : null,

                        'promotion_name' =>
                            (string) $row->promotion_name,

                        'packages_sold' =>
                            (int) $row->packages_sold,

                        'gross_sales' =>
                            round(
                                (float) $row->gross_sales,
                                2,
                            ),
                    ];
                },
            )
            ->values()
            ->all();
    }

    /**
     * Ranking de tamaños según pizzas físicas vendidas.
     *
     * Para una promoción, el tamaño almacenado en order_items se aplica
     * a cada pizza contenida en order_promotion_items.
     *
     * @return list<array{
     *     size_id: int|null,
     *     size_name: string,
     *     pizza_units: int
     * }>
     */
    private function sizePerformance(
        AnalyticsDateRangeData $range
    ): array {
        $standaloneSizes =
            $this->deliveredOrderItemsBase($range)
                ->whereNull(
                    'order_items.promotion_id'
                )
                ->whereNotNull(
                    'order_items.size_id'
                )
                ->selectRaw(
                    '
                    order_items.size_id,
                    COALESCE(
                        order_items.size_name,
                        "Tamaño sin nombre"
                    ) AS size_name,
                    SUM(order_items.quantity) AS pizza_units
                    '
                )
                ->groupBy(
                    'order_items.size_id',
                    'order_items.size_name',
                );

        $promotionSizes =
            $this->deliveredOrderItemsBase($range)
                ->join(
                    'order_promotion_items',
                    'order_promotion_items.order_item_id',
                    '=',
                    'order_items.id',
                )
                ->whereNotNull(
                    'order_items.promotion_id'
                )
                ->whereNotNull(
                    'order_items.size_id'
                )
                ->selectRaw(
                    '
                    order_items.size_id,
                    COALESCE(
                        order_items.size_name,
                        "Tamaño sin nombre"
                    ) AS size_name,
                    SUM(order_items.quantity) AS pizza_units
                    '
                )
                ->groupBy(
                    'order_items.size_id',
                    'order_items.size_name',
                );

        $union = $standaloneSizes
            ->unionAll($promotionSizes);

        return DB::query()
            ->fromSub(
                $union,
                'size_performance',
            )
            ->selectRaw(
                '
                size_id,
                size_name,
                SUM(pizza_units) AS pizza_units
                '
            )
            ->groupBy(
                'size_id',
                'size_name',
            )
            ->orderByDesc(
                'pizza_units'
            )
            ->orderBy(
                'size_name'
            )
            ->get()
            ->map(
                static function (
                    object $row
                ): array {
                    return [
                        'size_id' =>
                            $row->size_id !== null
                                ? (int) $row->size_id
                                : null,

                        'size_name' =>
                            (string) $row->size_name,

                        'pizza_units' =>
                            (int) $row->pizza_units,
                    ];
                },
            )
            ->values()
            ->all();
    }

    /**
     * Consulta base de items pertenecientes exclusivamente a pedidos
     * entregados durante el periodo solicitado.
     */
    private function deliveredOrderItemsBase(
        AnalyticsDateRangeData $range
    ): Builder {
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
            );
    }

    /**
     * @param list<array<string, int|float|string|null>> $pizzas
     * @param list<array<string, int|float|string|null>> $promotions
     * @param list<array<string, int|float|string|null>> $sizes
     *
     * @return array<string, mixed>
     */
    private function buildSummary(
        array $pizzas,
        array $promotions,
        array $sizes,
    ): array {
        $totalPizzaUnits = array_reduce(
            $pizzas,
            static fn (
                float $carry,
                array $pizza
            ): float =>
                $carry
                + (float) $pizza['equivalent_units'],
            0.0,
        );

        $totalPromotionPackages = array_reduce(
            $promotions,
            static fn (
                int $carry,
                array $promotion
            ): int =>
                $carry
                + (int) $promotion['packages_sold'],
            0,
        );

        $promotionGrossSales = array_reduce(
            $promotions,
            static fn (
                float $carry,
                array $promotion
            ): float =>
                $carry
                + (float) $promotion['gross_sales'],
            0.0,
        );

        $topPizza = $pizzas[0] ?? null;
        $topPromotion = $promotions[0] ?? null;
        $topSize = $sizes[0] ?? null;

        return [
            'total_pizza_units' =>
                round($totalPizzaUnits, 2),

            'unique_pizzas_sold' =>
                count($pizzas),

            'total_promotion_packages' =>
                $totalPromotionPackages,

            'promotion_gross_sales' =>
                round($promotionGrossSales, 2),

            'top_pizza' =>
                $topPizza,

            'top_promotion' =>
                $topPromotion,

            'top_size' =>
                $topSize,
        ];
    }
}
