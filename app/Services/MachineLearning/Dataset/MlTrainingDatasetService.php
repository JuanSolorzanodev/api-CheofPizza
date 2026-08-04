<?php

declare(strict_types=1);

namespace App\Services\MachineLearning\Dataset;

use App\Models\MlDailyFeature;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class MlTrainingDatasetService
{
    /**
     * Construye el dataset cronológico que posteriormente Laravel
     * enviará al microservicio FastAPI para entrenamiento.
     *
     * @return array{
     *     schema_version: string,
     *     generated_at: string,
     *     timezone: string,
     *     maturity: array{
     *         status: string,
     *         label: string,
     *         confidence: string,
     *         collected_days: int,
     *         active_days: int,
     *         first_date: string|null,
     *         last_date: string|null,
     *         minimum_training_days: int,
     *         recommended_training_days: int,
     *         can_train_experimental: bool,
     *         can_train_operational: bool
     *     },
     *     summary: array{
     *         records: int,
     *         active_days: int,
     *         empty_days: int,
     *         total_delivered_orders: int,
     *         total_cancelled_orders: int,
     *         total_pizzas_sold: int,
     *         total_net_sales: float
     *     },
     *     records: list<array<string, int|float|string>>
     * }
     */
    public function build(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $limit = 365,
        bool $includeEmptyDays = true,
    ): array {
        $rows = $this->query(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            includeEmptyDays: $includeEmptyDays,
        )
            ->orderByDesc('date')
            ->limit($limit)
            ->get()
            ->sortBy('date')
            ->values();

        $activeDays = $rows->filter(
            static fn (
                MlDailyFeature $feature
            ): bool =>
                $feature->delivered_orders > 0
                || $feature->total_pizzas_sold > 0,
        );

        $collectedDays = $rows->count();
        $activeDaysCount = $activeDays->count();

        $firstDate = $rows
            ->first()
            ?->date
            ?->toDateString();

        $lastDate = $rows
            ->last()
            ?->date
            ?->toDateString();

        return [
            'schema_version' =>
                '1.0',

            'generated_at' =>
                now()->toIso8601String(),

            'timezone' =>
                (string) config(
                    'app.timezone',
                    'America/Guayaquil',
                ),

            'maturity' =>
                $this->maturity(
                    collectedDays:
                        $collectedDays,

                    activeDays:
                        $activeDaysCount,

                    firstDate:
                        $firstDate,

                    lastDate:
                        $lastDate,
                ),

            'summary' => [
                'records' =>
                    $collectedDays,

                'active_days' =>
                    $activeDaysCount,

                'empty_days' =>
                    $collectedDays
                    - $activeDaysCount,

                'total_delivered_orders' =>
                    (int) $rows->sum(
                        'delivered_orders',
                    ),

                'total_cancelled_orders' =>
                    (int) $rows->sum(
                        'cancelled_orders',
                    ),

                'total_pizzas_sold' =>
                    (int) $rows->sum(
                        'total_pizzas_sold',
                    ),

                'total_net_sales' =>
                    round(
                        (float) $rows->sum(
                            static fn (
                                MlDailyFeature $feature
                            ): float =>
                                (float) $feature
                                    ->net_sales,
                        ),
                        2,
                    ),
            ],

            'records' =>
                $rows
                    ->map(
                        fn (
                            MlDailyFeature $feature
                        ): array =>
                            $this->serializeRecord(
                                $feature,
                            ),
                    )
                    ->all(),
        ];
    }

    /**
     * @return Builder<MlDailyFeature>
     */
    private function query(
        ?string $dateFrom,
        ?string $dateTo,
        bool $includeEmptyDays,
    ): Builder {
        return MlDailyFeature::query()
            ->when(
                $dateFrom !== null,
                static fn (
                    Builder $query
                ): Builder =>
                    $query->whereDate(
                        'date',
                        '>=',
                        $dateFrom,
                    ),
            )
            ->when(
                $dateTo !== null,
                static fn (
                    Builder $query
                ): Builder =>
                    $query->whereDate(
                        'date',
                        '<=',
                        $dateTo,
                    ),
            )
            ->when(
                !$includeEmptyDays,
                static fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        static function (
                            Builder $nested
                        ): void {
                            $nested
                                ->where(
                                    'delivered_orders',
                                    '>',
                                    0,
                                )
                                ->orWhere(
                                    'total_pizzas_sold',
                                    '>',
                                    0,
                                );
                        },
                    ),
            );
    }

    /**
     * @return array<string, int|float|string>
     */
    private function serializeRecord(
        MlDailyFeature $feature,
    ): array {
        $date =
            CarbonImmutable::instance(
                $feature->date,
            );

        return [
            /*
             * Datos temporales.
             */
            'date' =>
                $date->toDateString(),

            'day_of_week' =>
                $date->dayOfWeekIso,

            'week_of_year' =>
                $date->isoWeek(),

            'month' =>
                $date->month,

            'day_of_month' =>
                $date->day,

            'is_weekend' =>
                $date->isWeekend()
                    ? 1
                    : 0,

            /*
             * Objetivos principales de predicción.
             */
            'total_pizzas_sold' =>
                $feature
                    ->total_pizzas_sold,

            'mini_sales' =>
                $feature
                    ->mini_sales,

            'small_sales' =>
                $feature
                    ->small_sales,

            'medium_sales' =>
                $feature
                    ->medium_sales,

            'family_sales' =>
                $feature
                    ->family_sales,

            'giant_sales' =>
                $feature
                    ->giant_sales,

            /*
             * Características comerciales y operativas.
             */
            'basic_sales' =>
                $feature
                    ->basic_sales,

            'special_sales' =>
                $feature
                    ->special_sales,

            'promotion_sales' =>
                $feature
                    ->promotion_sales,

            'regular_sales' =>
                $feature
                    ->regular_sales,

            'delivered_orders' =>
                $feature
                    ->delivered_orders,

            'cancelled_orders' =>
                $feature
                    ->cancelled_orders,

            'pickup_orders' =>
                $feature
                    ->pickup_orders,

            'delivery_orders' =>
                $feature
                    ->delivery_orders,

            'net_sales' =>
                round(
                    (float) $feature
                        ->net_sales,
                    2,
                ),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     confidence: string,
     *     collected_days: int,
     *     active_days: int,
     *     first_date: string|null,
     *     last_date: string|null,
     *     minimum_training_days: int,
     *     recommended_training_days: int,
     *     can_train_experimental: bool,
     *     can_train_operational: bool
     * }
     */
    private function maturity(
        int $collectedDays,
        int $activeDays,
        ?string $firstDate,
        ?string $lastDate,
    ): array {
        $minimumTrainingDays = 14;
        $recommendedTrainingDays = 30;

        [$status, $label, $confidence] =
            match (true) {
                $activeDays < 14 => [
                    'collecting',
                    'Recopilando datos',
                    'insufficient',
                ],

                $activeDays < 30 => [
                    'experimental',
                    'Pronóstico experimental',
                    'low',
                ],

                $activeDays < 60 => [
                    'initial',
                    'Modelo inicial',
                    'medium_low',
                ],

                $activeDays < 90 => [
                    'validation',
                    'Modelo en validación',
                    'medium',
                ],

                default => [
                    'operational',
                    'Modelo operativo',
                    'high',
                ],
            };

        return [
            'status' =>
                $status,

            'label' =>
                $label,

            'confidence' =>
                $confidence,

            'collected_days' =>
                $collectedDays,

            /*
             * Para madurez usamos días con actividad real.
             * Un día vacío sigue siendo útil para la serie temporal,
             * pero no aporta el mismo volumen de evidencia.
             */
            'active_days' =>
                $activeDays,

            'first_date' =>
                $firstDate,

            'last_date' =>
                $lastDate,

            'minimum_training_days' =>
                $minimumTrainingDays,

            'recommended_training_days' =>
                $recommendedTrainingDays,

            'can_train_experimental' =>
                $activeDays
                >= $minimumTrainingDays,

            'can_train_operational' =>
                $activeDays >= 90,
        ];
    }
}
