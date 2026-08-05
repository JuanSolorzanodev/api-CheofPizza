<?php

declare(strict_types=1);

namespace App\Services\MachineLearning\Dataset;

use App\Enums\OrderStatusName;
use App\Models\MlDailyFeature;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class DailySalesFeatureService
{
    /**
     * Consolida un día completo de operación.
     *
     * Reglas:
     * - Solo los pedidos actualmente entregados representan demanda real.
     * - Los pedidos cancelados se guardan como variable contextual.
     * - Una pizza mitad y mitad sigue contando como una pizza física.
     * - Cada pizza incluida en una promoción se multiplica por la cantidad
     *   de paquetes comprados.
     * - La operación es idempotente mediante updateOrCreate().
     */
    public function aggregate(
        CarbonInterface|string $date,
    ): MlDailyFeature {
        $businessDate = $this->normalizeDate(
            $date,
        );

        return DB::transaction(
            function () use (
                $businessDate,
            ): MlDailyFeature {
                $deliveredOrders =
                    $this->deliveredOrdersForDate(
                        $businessDate,
                    )->get();

                $cancelledOrders =
                    $this->cancelledOrdersForDate(
                        $businessDate,
                    )->count();

                $features = [
                    'total_pizzas_sold' => 0,

                    'mini_sales' => 0,

                    'small_sales' => 0,

                    'medium_sales' => 0,

                    'family_sales' => 0,

                    'giant_sales' => 0,

                    'basic_sales' => 0,

                    'special_sales' => 0,

                    'promotion_sales' => 0,

                    'regular_sales' => 0,

                    'delivered_orders' => $deliveredOrders->count(),

                    'cancelled_orders' => $cancelledOrders,

                    'net_sales' => round(
                        $deliveredOrders->sum(
                            static fn (
                                Order $order
                            ): float => (float) $order->total,
                        ),
                        2,
                    ),

                    'pickup_orders' => 0,

                    'delivery_orders' => 0,

                    'consolidated_at' => now(),

                    'source' => 'laravel_sales',
                ];

                foreach (
                    $deliveredOrders as $order
                ) {
                    $this->incrementDeliveryType(
                        features: $features,
                        order: $order,
                    );

                    foreach (
                        $order->orderItems as $orderItem
                    ) {
                        $quantity = max(
                            0,
                            (int) $orderItem->quantity,
                        );

                        if ($quantity === 0) {
                            continue;
                        }

                        if (
                            $orderItem->promotion_id
                            !== null
                        ) {
                            $promotionPizzaCount =
                                $orderItem
                                    ->orderPromotionItems
                                    ->count();

                            $physicalUnits =
                                $promotionPizzaCount
                                * $quantity;

                            if ($physicalUnits === 0) {
                                continue;
                            }

                            $features['promotion_sales'] += $physicalUnits;

                            $features['total_pizzas_sold'] += $physicalUnits;

                            $this->incrementSize(
                                features: $features,
                                sizeName: $orderItem->size_name,
                                units: $physicalUnits,
                            );

                            /*
                             * Las pizzas promocionales no guardan una
                             * categoría histórica en order_promotion_items.
                             * Por ahora no se asignan a basic/special para
                             * evitar inventar información retrospectiva.
                             */
                            continue;
                        }

                        if (
                            $orderItem->pizza_id
                            === null
                        ) {
                            continue;
                        }

                        /*
                         * Una mitad y mitad sigue siendo una pizza física,
                         * por eso se contabiliza solo por quantity.
                         */
                        $features['regular_sales'] += $quantity;

                        $features['total_pizzas_sold'] += $quantity;

                        $this->incrementSize(
                            features: $features,
                            sizeName: $orderItem->size_name,
                            units: $quantity,
                        );

                        $this->incrementCategory(
                            features: $features,
                            firstCategory: $orderItem
                                ->category_name,
                            secondCategory: $orderItem
                                ->category_name_second,
                            isHalfAndHalf: (bool) $orderItem
                                ->is_half_and_half,
                            units: $quantity,
                        );
                    }
                }

                $this->assertSizeTotalsMatch(
                    $features,
                    $businessDate,
                );

                return MlDailyFeature::query()
                    ->updateOrCreate(
                        [
                            'date' => $businessDate
                                ->toDateString(),
                        ],
                        $features,
                    );
            },
            attempts: 3,
        );
    }

    /**
     * Consolida un rango inclusivo de fechas.
     *
     * @return list<MlDailyFeature>
     */
    public function aggregateRange(
        CarbonInterface|string $from,
        CarbonInterface|string $to,
    ): array {
        $start = $this->normalizeDate(
            $from,
        );

        $end = $this->normalizeDate(
            $to,
        );

        if ($start->greaterThan($end)) {
            throw new RuntimeException(
                'La fecha inicial no puede ser posterior a la fecha final.',
            );
        }

        $results = [];

        for (
            $date = $start;
            $date->lessThanOrEqualTo($end);
            $date = $date->addDay()
        ) {
            $results[] =
                $this->aggregate($date);
        }

        return $results;
    }

    /**
     * Consulta los pedidos entregados creados durante el día comercial.
     *
     * Se utiliza ordered_at porque representa cuándo nació la demanda.
     * El pedido solo se incluye cuando su estado final actual es delivered.
     *
     * @return Builder<Order>
     */
    private function deliveredOrdersForDate(
        CarbonImmutable $date,
    ): Builder {
        return Order::query()
            ->whereHas(
                'orderStatus',
                static function (
                    Builder $query
                ): void {
                    $query->where(
                        'status_name',
                        OrderStatusName::Delivered->value,
                    );
                },
            )
            ->whereBetween(
                'ordered_at',
                [
                    $date->startOfDay(),
                    $date->endOfDay(),
                ],
            )
            ->with([
                'deliveryType:id,delivery_type_name',

                'orderItems:id,order_id,promotion_id,pizza_id,size_name,category_name,category_name_second,is_half_and_half,quantity',

                'orderItems.orderPromotionItems:id,order_item_id,pizza_id',
            ]);
    }

    /**
     * @return Builder<Order>
     */
    private function cancelledOrdersForDate(
        CarbonImmutable $date,
    ): Builder {
        return Order::query()
            ->whereHas(
                'orderStatus',
                static function (
                    Builder $query
                ): void {
                    $query->where(
                        'status_name',
                        OrderStatusName::Cancelled->value,
                    );
                },
            )
            ->whereBetween(
                'ordered_at',
                [
                    $date->startOfDay(),
                    $date->endOfDay(),
                ],
            );
    }

    /**
     * @param  array<string, int|float|string|null>  $features
     */
    private function incrementSize(
        array &$features,
        ?string $sizeName,
        int $units,
    ): void {
        $key = match ($this->normalizeLabel(
            $sizeName,
        )) {
            'personal',
            'mini' => 'mini_sales',

            'pequena',
            'small' => 'small_sales',

            'mediana',
            'medium' => 'medium_sales',

            'familiar',
            'family' => 'family_sales',

            'gigante',
            'giant' => 'giant_sales',

            default => null,
        };

        if ($key === null) {
            throw new RuntimeException(
                sprintf(
                    'No se reconoce el tamaño "%s" durante la consolidación.',
                    $sizeName ?? 'NULL',
                ),
            );
        }

        $features[$key] += $units;
    }

    /**
     * @param  array<string, int|float|string|null>  $features
     */
    private function incrementCategory(
        array &$features,
        ?string $firstCategory,
        ?string $secondCategory,
        bool $isHalfAndHalf,
        int $units,
    ): void {
        $firstKey =
            $this->categoryFeatureKey(
                $firstCategory,
            );

        if (! $isHalfAndHalf) {
            if ($firstKey !== null) {
                $features[$firstKey] +=
                    $units;
            }

            return;
        }

        $secondKey =
            $this->categoryFeatureKey(
                $secondCategory,
            );

        /*
         * Los campos históricos son enteros. Para no falsear datos con
         * mitades decimales, clasificamos la pizza completa así:
         *
         * - ambas mitades iguales: esa categoría;
         * - categorías distintas: special_sales, porque contiene al menos
         *   una mitad especial y representa mayor complejidad operativa.
         */
        if (
            $firstKey !== null
            && $firstKey === $secondKey
        ) {
            $features[$firstKey] +=
                $units;

            return;
        }

        if (
            $firstKey !== null
            || $secondKey !== null
        ) {
            $features['special_sales'] += $units;
        }
    }

    private function categoryFeatureKey(
        ?string $categoryName,
    ): ?string {
        return match ($this->normalizeLabel(
            $categoryName,
        )) {
            'sencilla',
            'sencillas',
            'basic',
            'basica',
            'basicas' => 'basic_sales',

            'especial',
            'especiales',
            'special' => 'special_sales',

            default => null,
        };
    }

    /**
     * @param  array<string, int|float|string|null>  $features
     */
    private function incrementDeliveryType(
        array &$features,
        Order $order,
    ): void {
        $deliveryType =
            $this->normalizeLabel(
                $order
                    ->deliveryType
                    ?->delivery_type_name,
            );

        match ($deliveryType) {
            'pickup',
            'retiro',
            'retirar',
            'local' => $features['pickup_orders']++,

            'delivery',
            'domicilio',
            'entrega a domicilio' => $features['delivery_orders']++,

            default => null,
        };
    }

    /**
     * @param  array<string, int|float|string|null>  $features
     */
    private function assertSizeTotalsMatch(
        array $features,
        CarbonImmutable $date,
    ): void {
        $sizeTotal =
            (int) $features['mini_sales']
            + (int) $features['small_sales']
            + (int) $features['medium_sales']
            + (int) $features['family_sales']
            + (int) $features['giant_sales'];

        if (
            $sizeTotal
            !== (int) $features['total_pizzas_sold']
        ) {
            throw new RuntimeException(
                sprintf(
                    'La consolidación de %s produjo un total inconsistente: total=%d, tamaños=%d.',
                    $date->toDateString(),
                    (int) $features['total_pizzas_sold'],
                    $sizeTotal,
                ),
            );
        }
    }

    private function normalizeLabel(
        ?string $value,
    ): string {
        if ($value === null) {
            return '';
        }

        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    private function normalizeDate(
        CarbonInterface|string $date,
    ): CarbonImmutable {
        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance(
                $date,
            )->startOfDay();
        }

        return CarbonImmutable::parse(
            $date,
            config('app.timezone'),
        )->startOfDay();
    }
}
