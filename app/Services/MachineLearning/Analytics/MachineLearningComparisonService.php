<?php

declare(strict_types=1);

namespace App\Services\MachineLearning\Analytics;

use App\Models\MlDailyFeature;
use App\Models\MlDailyPrediction;
use App\Models\MlModelRun;
use App\Services\MachineLearning\Dataset\DailySalesFeatureService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MachineLearningComparisonService
{
    public function __construct(
        private readonly DailySalesFeatureService $dailySalesFeatureService,
    ) {}

    /**
     * Compara las predicciones del modelo activo con las ventas
     * reales consolidadas por Laravel.
     *
     * Las fechas pasadas se incluyen en las métricas definitivas.
     * La fecha actual se muestra como acumulado parcial.
     * Las fechas futuras se muestran como pendientes.
     *
     * @return array<string, mixed>
     */
    public function compare(
        string $dateFrom,
        string $dateTo,
    ): array {
        $timezone = (string) config(
            'app.timezone',
            'America/Guayaquil',
        );

        $from = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $dateFrom,
            $timezone,
        )->startOfDay();

        $to = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $dateTo,
            $timezone,
        )->startOfDay();

        $today = CarbonImmutable::today(
            $timezone,
        );

        /*
         * Solo se utiliza el modelo que actualmente está activo.
         */
        $run = MlModelRun::query()
            ->where(
                'status',
                MlModelRun::STATUS_COMPLETED,
            )
            ->where(
                'is_active',
                true,
            )
            ->latest(
                'generated_at',
            )
            ->first();

        if ($run === null) {
            return $this->emptyResult(
                from: $from,
                to: $to,
                timezone: $timezone,
            );
        }

        $predictions = MlDailyPrediction::query()
            ->where(
                'ml_model_run_id',
                $run->id,
            )
            ->whereBetween(
                'prediction_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ],
            )
            ->orderBy(
                'prediction_date',
            )
            ->get();

        if (
            $predictions->isEmpty()
        ) {
            return $this->resultWithoutPredictions(
                run: $run,
                from: $from,
                to: $to,
                timezone: $timezone,
            );
        }

        /*
         * Nunca consolidamos fechas futuras.
         *
         * Si el rango termina después de hoy, solo se consolida
         * hasta la fecha actual.
         */
        $lastConsolidatableDate =
            $to->lessThan(
                $today,
            )
                ? $to
                : $today;

        /*
         * Se vuelve a consolidar el rango para que la comparación
         * utilice la información real más reciente.
         *
         * aggregateRange() es idempotente porque internamente usa
         * updateOrCreate().
         */
        if (
            $from->lessThanOrEqualTo(
                $lastConsolidatableDate,
            )
        ) {
            $this->dailySalesFeatureService
                ->aggregateRange(
                    $from,
                    $lastConsolidatableDate,
                );
        }

        /** @var Collection<string, MlDailyFeature> $actualByDate */
        $actualByDate = MlDailyFeature::query()
            ->whereBetween(
                'date',
                [
                    $from->toDateString(),
                    $lastConsolidatableDate
                        ->toDateString(),
                ],
            )
            ->get()
            ->keyBy(
                static fn (
                    MlDailyFeature $feature,
                ): string => $feature
                    ->date
                    ->toDateString(),
            );

        $days = [];
        $completedRows = [];

        foreach (
            $predictions as $prediction
        ) {
            $date = CarbonImmutable::parse(
                $prediction->prediction_date,
                $timezone,
            )->startOfDay();

            $status = match (true) {
                $date->lessThan(
                    $today,
                ) => 'completed',

                $date->equalTo(
                    $today,
                ) => 'in_progress',

                default => 'pending',
            };

            $feature = $actualByDate->get(
                $date->toDateString(),
            );

            /*
             * En fechas futuras no se devuelve cero como venta real,
             * porque eso produciría una comparación falsa.
             */
            $actualTotal =
                $status === 'pending'
                    ? null
                    : (int) (
                        $feature
                            ?->total_pizzas_sold
                        ?? 0
                    );

            $predictedTotal =
                (int) $prediction
                    ->total_pizzas;

            $difference =
                $actualTotal === null
                    ? null
                    : $actualTotal -
                        $predictedTotal;

            $absoluteError =
                $difference === null
                    ? null
                    : abs(
                        $difference,
                    );

            $accuracy =
                $absoluteError === null
                    ? null
                    : $this
                        ->accuracyPercentage(
                            predicted: $predictedTotal,

                            actual: $actualTotal,
                        );

            $row = [
                'date' => $date->toDateString(),

                'day_of_week' => $prediction
                    ->day_of_week,

                'status' => $status,

                'predicted_total' => $predictedTotal,

                'actual_total' => $actualTotal,

                'difference' => $difference,

                'absolute_error' => $absoluteError,

                'accuracy_percentage' => $accuracy,

                'predicted_sizes' => [
                    'mini' => (int) $prediction
                        ->mini_pizzas,

                    'small' => (int) $prediction
                        ->small_pizzas,

                    'medium' => (int) $prediction
                        ->medium_pizzas,

                    'family' => (int) $prediction
                        ->family_pizzas,

                    'giant' => (int) $prediction
                        ->giant_pizzas,
                ],

                'actual_sizes' => $status === 'pending'
                        ? null
                        : [
                            'mini' => (int) (
                                $feature
                                    ?->mini_sales
                                ?? 0
                            ),

                            'small' => (int) (
                                $feature
                                    ?->small_sales
                                ?? 0
                            ),

                            'medium' => (int) (
                                $feature
                                    ?->medium_sales
                                ?? 0
                            ),

                            'family' => (int) (
                                $feature
                                    ?->family_sales
                                ?? 0
                            ),

                            'giant' => (int) (
                                $feature
                                    ?->giant_sales
                                ?? 0
                            ),
                        ],

                /*
                 * Estos valores permiten relacionar la comparación
                 * predictiva con la analítica administrativa.
                 */
                'actual_net_sales' => $status === 'pending'
                        ? null
                        : (float) (
                            $feature
                                ?->net_sales
                            ?? 0
                        ),

                'delivered_orders' => $status === 'pending'
                        ? null
                        : (int) (
                            $feature
                                ?->delivered_orders
                            ?? 0
                        ),
            ];

            $days[] = $row;

            /*
             * Solo las fechas completamente finalizadas intervienen
             * en MAE, totales y precisión promedio.
             */
            if (
                $status ===
                'completed'
            ) {
                $completedRows[] =
                    $row;
            }
        }

        return [
            'period' => [
                'date_from' => $from->toDateString(),

                'date_to' => $to->toDateString(),

                'timezone' => $timezone,

                'days' => $from->diffInDays(
                    $to,
                ) + 1,
            ],

            'model' => $this->modelData(
                $run,
            ),

            'summary' => $this->summary(
                allRows: $days,

                completedRows: $completedRows,
            ),

            'days' => $days,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $allRows
     * @param  list<array<string, mixed>>  $completedRows
     * @return array<string, int|float>
     */
    private function summary(
        array $allRows,
        array $completedRows,
    ): array {
        $predictedTotal =
            array_sum(
                array_column(
                    $completedRows,
                    'predicted_total',
                ),
            );

        $actualTotal =
            array_sum(
                array_column(
                    $completedRows,
                    'actual_total',
                ),
            );

        $absoluteErrorTotal =
            array_sum(
                array_column(
                    $completedRows,
                    'absolute_error',
                ),
            );

        $accuracyTotal =
            array_sum(
                array_column(
                    $completedRows,
                    'accuracy_percentage',
                ),
            );

        $daysCompared =
            count(
                $completedRows,
            );

        return [
            'days_with_prediction' => count(
                $allRows,
            ),

            'days_compared' => $daysCompared,

            'days_in_progress' => count(
                array_filter(
                    $allRows,

                    static fn (
                        array $row,
                    ): bool => $row['status']
                        ===
                        'in_progress',
                ),
            ),

            'days_pending' => count(
                array_filter(
                    $allRows,

                    static fn (
                        array $row,
                    ): bool => $row['status']
                        ===
                        'pending',
                ),
            ),

            'predicted_total' => $predictedTotal,

            'actual_total' => $actualTotal,

            'difference' => $actualTotal -
                $predictedTotal,

            'absolute_error_total' => $absoluteErrorTotal,

            'mae' => $daysCompared > 0
                    ? round(
                        $absoluteErrorTotal /
                        $daysCompared,
                        2,
                    )
                    : 0.0,

            'average_accuracy_percentage' => $daysCompared > 0
                    ? round(
                        $accuracyTotal /
                        $daysCompared,
                        2,
                    )
                    : 0.0,
        ];
    }

    /**
     * Precisión porcentual basada en el error relativo
     * respecto a la cantidad pronosticada.
     */
    private function accuracyPercentage(
        int $predicted,
        int $actual,
    ): float {
        if (
            $predicted === 0
        ) {
            return $actual === 0
                ? 100.0
                : 0.0;
        }

        return round(
            max(
                0,
                100 - (
                    (
                        abs(
                            $actual -
                            $predicted,
                        )
                        /
                        $predicted
                    )
                    *
                    100
                ),
            ),
            2,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function modelData(
        MlModelRun $run,
    ): array {
        return [
            'uuid' => $run->uuid,

            'algorithm' => $run->algorithm,

            'version' => $run->version,

            'generated_at' => $run
                ->generated_at
                ?->toIso8601String(),

            'forecast_from' => $run
                ->forecast_from
                ?->toDateString(),

            'forecast_until' => $run
                ->forecast_until
                ?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
    ): array {
        return [
            'period' => [
                'date_from' => $from->toDateString(),

                'date_to' => $to->toDateString(),

                'timezone' => $timezone,

                'days' => $from->diffInDays(
                    $to,
                ) + 1,
            ],

            'model' => null,

            'summary' => $this->summary(
                allRows: [],
                completedRows: [],
            ),

            'days' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultWithoutPredictions(
        MlModelRun $run,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
    ): array {
        $result =
            $this->emptyResult(
                from: $from,

                to: $to,

                timezone: $timezone,
            );

        $result['model'] =
            $this->modelData(
                $run,
            );

        return $result;
    }
}
