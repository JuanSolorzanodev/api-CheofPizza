<?php

declare(strict_types=1);

use App\Models\MlDailyPrediction;
use App\Models\MlModelRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /*
     * Se fija la fecha actual para probar de forma determinista:
     *
     * - 3 y 4 de agosto: días finalizados.
     * - 5 de agosto: día en curso.
     * - 6 de agosto: día futuro.
     */
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse(
            '2026-08-05 12:00:00',
            'America/Guayaquil',
        ),
    );
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * Crea una ejecución de modelo activa.
 *
 * @param  array<string, mixed>  $overrides
 */
function createComparisonModelRun(
    array $overrides = [],
): MlModelRun {
    return MlModelRun::query()
        ->create(
            array_merge(
                [
                    'uuid' => (string) Str::uuid(),

                    'source_hash' => hash(
                        'sha256',
                        Str::random(40),
                    ),

                    'source' => MlModelRun::SOURCE_ML_SERVICE,

                    'status' => MlModelRun::STATUS_COMPLETED,

                    'algorithm' => 'RandomForest',

                    'target' => MlModelRun::TARGET_TOTAL_UNITS,

                    'version' => 'comparison-test',

                    'trained_from' => '2026-01-01',

                    'trained_until' => '2026-07-31',

                    'training_records' => 212,

                    'forecast_days' => 3,

                    'forecast_from' => '2026-08-03',

                    'forecast_until' => '2026-08-05',

                    'selection_score' => 1.5,

                    'mae' => 2.1,

                    'rmse' => 2.8,

                    'smape' => 8.5,

                    'r2' => 0.91,

                    'cv_mae' => 2.3,

                    'cv_rmse' => 3.0,

                    'generated_at' => '2026-08-02 22:00:00',

                    'activated_at' => '2026-08-02 22:05:00',

                    'is_active' => true,

                    'models' => [],

                    'summary' => [],

                    'recommendations' => [],

                    'metadata' => [],
                ],
                $overrides,
            ),
        );
}

/**
 * Crea una predicción diaria completa.
 *
 * La tabla ml_daily_predictions tiene columnas obligatorias
 * sin valor predeterminado:
 *
 * - basic
 * - special
 * - estimated_promotions
 * - estimated_regular
 *
 * Por eso siempre deben incluirse en los datos de prueba.
 *
 * @param  array<string, mixed>  $overrides
 */
function createComparisonPrediction(
    MlModelRun $run,
    array $overrides = [],
): MlDailyPrediction {
    return MlDailyPrediction::query()
        ->create(
            array_merge(
                [
                    'ml_model_run_id' => $run->id,

                    'prediction_date' => '2026-08-03',

                    'day_of_week' => 'Lunes',

                    'total_pizzas' => 10,

                    'mini_pizzas' => 1,

                    'small_pizzas' => 2,

                    'medium_pizzas' => 4,

                    'family_pizzas' => 3,

                    'giant_pizzas' => 0,

                    'basic' => 6,

                    'special' => 4,

                    'estimated_promotions' => 2,

                    'estimated_regular' => 8,

                    'lower_bound' => null,

                    'upper_bound' => null,

                    'confidence_score' => null,

                    'metadata' => [],
                ],
                $overrides,
            ),
        );
}

/**
 * Crea un modelo activo con tres pronósticos consecutivos:
 *
 * - 2026-08-03: finalizado.
 * - 2026-08-04: finalizado.
 * - 2026-08-05: en curso.
 */
function createActiveComparisonForecast(): MlModelRun
{
    $run = createComparisonModelRun();

    createComparisonPrediction(
        $run,
        [
            'prediction_date' => '2026-08-03',

            'day_of_week' => 'Lunes',

            'total_pizzas' => 10,

            'mini_pizzas' => 1,

            'small_pizzas' => 2,

            'medium_pizzas' => 4,

            'family_pizzas' => 3,

            'giant_pizzas' => 0,

            'basic' => 6,

            'special' => 4,

            'estimated_promotions' => 2,

            'estimated_regular' => 8,
        ],
    );

    createComparisonPrediction(
        $run,
        [
            'prediction_date' => '2026-08-04',

            'day_of_week' => 'Martes',

            'total_pizzas' => 8,

            'mini_pizzas' => 0,

            'small_pizzas' => 2,

            'medium_pizzas' => 4,

            'family_pizzas' => 2,

            'giant_pizzas' => 0,

            'basic' => 5,

            'special' => 3,

            'estimated_promotions' => 1,

            'estimated_regular' => 7,
        ],
    );

    createComparisonPrediction(
        $run,
        [
            'prediction_date' => '2026-08-05',

            'day_of_week' => 'Miércoles',

            'total_pizzas' => 12,

            'mini_pizzas' => 1,

            'small_pizzas' => 3,

            'medium_pizzas' => 5,

            'family_pizzas' => 3,

            'giant_pizzas' => 0,

            'basic' => 7,

            'special' => 5,

            'estimated_promotions' => 3,

            'estimated_regular' => 9,
        ],
    );

    return $run;
}

it(
    'requires authentication to view the predictive comparison',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson(
                '/api/v1/admin/machine-learning/comparison'
                .'?date_from=2026-08-03'
                .'&date_to=2026-08-05',
            )
            ->assertUnauthorized();
    },
);

it(
    'forbids customers from viewing the predictive comparison',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/comparison'
                .'?date_from=2026-08-03'
                .'&date_to=2026-08-05',
            )
            ->assertForbidden();
    },
);

it(
    'validates the comparison date range',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/comparison'
                .'?date_from=2026-08-05'
                .'&date_to=2026-08-03',
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    },
);

it(
    'limits comparison periods to thirty one days',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/comparison'
                .'?date_from=2026-06-01'
                .'&date_to=2026-08-05',
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    },
);

it(
    'returns an empty comparison when no active model exists',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/comparison'
                .'?date_from=2026-08-03'
                .'&date_to=2026-08-05',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.model',
                null,
            )
            ->assertJsonPath(
                'data.period.date_from',
                '2026-08-03',
            )
            ->assertJsonPath(
                'data.period.date_to',
                '2026-08-05',
            )
            ->assertJsonPath(
                'data.period.days',
                3,
            )
            ->assertJsonPath(
                'data.summary.days_with_prediction',
                0,
            )
            ->assertJsonPath(
                'data.summary.days_compared',
                0,
            )
            ->assertJsonPath(
                'data.summary.days_in_progress',
                0,
            )
            ->assertJsonPath(
                'data.summary.days_pending',
                0,
            )
            ->assertJsonCount(
                0,
                'data.days',
            );
    },
);

it(
    'compares completed days and excludes the current day from final metrics',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        createActiveComparisonForecast();

        /*
         * En esta prueba no se crean pedidos reales.
         *
         * DailySalesFeatureService consolidará los días con cero
         * ventas, permitiendo comprobar los estados y las métricas.
         *
         * Los días 3 y 4 se consideran finalizados.
         * El día 5 se considera en curso y no entra al MAE final.
         */
        $response = $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/comparison'
                .'?date_from=2026-08-03'
                .'&date_to=2026-08-05',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.model.algorithm',
                'RandomForest',
            )
            ->assertJsonPath(
                'data.model.version',
                'comparison-test',
            )
            ->assertJsonPath(
                'data.period.date_from',
                '2026-08-03',
            )
            ->assertJsonPath(
                'data.period.date_to',
                '2026-08-05',
            )
            ->assertJsonPath(
                'data.period.days',
                3,
            )
            ->assertJsonPath(
                'data.summary.days_with_prediction',
                3,
            )
            ->assertJsonPath(
                'data.summary.days_compared',
                2,
            )
            ->assertJsonPath(
                'data.summary.days_in_progress',
                1,
            )
            ->assertJsonPath(
                'data.summary.days_pending',
                0,
            )
            ->assertJsonPath(
                'data.summary.predicted_total',
                18,
            )
            ->assertJsonPath(
                'data.summary.actual_total',
                0,
            )
            ->assertJsonPath(
                'data.summary.difference',
                -18,
            )
            ->assertJsonPath(
                'data.summary.absolute_error_total',
                18,
            )
            ->assertJsonPath(
                'data.summary.mae',
                9,
            )
            ->assertJsonPath(
                'data.summary.average_accuracy_percentage',
                0,
            )
            ->assertJsonPath(
                'data.days.0.date',
                '2026-08-03',
            )
            ->assertJsonPath(
                'data.days.0.status',
                'completed',
            )
            ->assertJsonPath(
                'data.days.0.predicted_total',
                10,
            )
            ->assertJsonPath(
                'data.days.0.actual_total',
                0,
            )
            ->assertJsonPath(
                'data.days.0.difference',
                -10,
            )
            ->assertJsonPath(
                'data.days.0.absolute_error',
                10,
            )
            ->assertJsonPath(
                'data.days.0.accuracy_percentage',
                0,
            )
            ->assertJsonPath(
                'data.days.0.predicted_sizes.mini',
                1,
            )
            ->assertJsonPath(
                'data.days.0.predicted_sizes.small',
                2,
            )
            ->assertJsonPath(
                'data.days.0.predicted_sizes.medium',
                4,
            )
            ->assertJsonPath(
                'data.days.0.predicted_sizes.family',
                3,
            )
            ->assertJsonPath(
                'data.days.0.predicted_sizes.giant',
                0,
            )
            ->assertJsonPath(
                'data.days.1.date',
                '2026-08-04',
            )
            ->assertJsonPath(
                'data.days.1.status',
                'completed',
            )
            ->assertJsonPath(
                'data.days.1.predicted_total',
                8,
            )
            ->assertJsonPath(
                'data.days.1.actual_total',
                0,
            )
            ->assertJsonPath(
                'data.days.2.date',
                '2026-08-05',
            )
            ->assertJsonPath(
                'data.days.2.status',
                'in_progress',
            )
            ->assertJsonPath(
                'data.days.2.predicted_total',
                12,
            )
            ->assertJsonPath(
                'data.days.2.actual_total',
                0,
            )
            ->assertJsonCount(
                3,
                'data.days',
            );
    },
);

it(
    'marks future predictions as pending without assigning false sales',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $run = createComparisonModelRun([
            'version' => 'future-test',

            'forecast_days' => 1,

            'forecast_from' => '2026-08-06',

            'forecast_until' => '2026-08-06',

            'generated_at' => '2026-08-05 01:00:00',

            'activated_at' => '2026-08-05 01:05:00',
        ]);

        createComparisonPrediction(
            $run,
            [
                'prediction_date' => '2026-08-06',

                'day_of_week' => 'Jueves',

                'total_pizzas' => 15,

                'mini_pizzas' => 1,

                'small_pizzas' => 3,

                'medium_pizzas' => 6,

                'family_pizzas' => 4,

                'giant_pizzas' => 1,

                'basic' => 9,

                'special' => 6,

                'estimated_promotions' => 4,

                'estimated_regular' => 11,
            ],
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/comparison'
                .'?date_from=2026-08-06'
                .'&date_to=2026-08-06',
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.model.version',
                'future-test',
            )
            ->assertJsonPath(
                'data.summary.days_with_prediction',
                1,
            )
            ->assertJsonPath(
                'data.summary.days_compared',
                0,
            )
            ->assertJsonPath(
                'data.summary.days_in_progress',
                0,
            )
            ->assertJsonPath(
                'data.summary.days_pending',
                1,
            )
            ->assertJsonPath(
                'data.summary.predicted_total',
                0,
            )
            ->assertJsonPath(
                'data.summary.actual_total',
                0,
            )
            ->assertJsonPath(
                'data.summary.mae',
                0,
            )
            ->assertJsonPath(
                'data.days.0.date',
                '2026-08-06',
            )
            ->assertJsonPath(
                'data.days.0.status',
                'pending',
            )
            ->assertJsonPath(
                'data.days.0.predicted_total',
                15,
            )
            ->assertJsonPath(
                'data.days.0.actual_total',
                null,
            )
            ->assertJsonPath(
                'data.days.0.difference',
                null,
            )
            ->assertJsonPath(
                'data.days.0.absolute_error',
                null,
            )
            ->assertJsonPath(
                'data.days.0.accuracy_percentage',
                null,
            )
            ->assertJsonPath(
                'data.days.0.actual_sizes',
                null,
            )
            ->assertJsonPath(
                'data.days.0.actual_net_sales',
                null,
            )
            ->assertJsonPath(
                'data.days.0.delivered_orders',
                null,
            )
            ->assertJsonCount(
                1,
                'data.days',
            );
    },
);
