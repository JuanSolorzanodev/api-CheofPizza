<?php

declare(strict_types=1);

use App\Models\MlModelRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function cheofPizzaForecastPayload(
    float $mae = 3.7195,
    string $generatedAt = '2026-07-29T04:02:22+00:00',
): array {
    return [
        'generated_at' => $generatedAt,

        'trained_from' => '2025-03-22',

        'trained_until' => '2026-02-07',

        'historical_days' => 323,

        'forecast_days' => 7,

        'models' => [
            'family' => [
                'name' => 'RandomForest',
                'selection_score' => 1.7319,
                'test_mae' => 1.8856,
                'test_rmse' => 2.6651,
                'test_smape' => 112.4258,
                'test_r2' => -0.0157,
                'cv_mae' => 1.5014,
                'cv_rmse' => 1.8506,
            ],

            'medium' => [
                'name' => 'RandomForest',
                'selection_score' => 2.1210,
                'test_mae' => 2.1423,
                'test_rmse' => 2.6605,
                'test_smape' => 93.1341,
                'test_r2' => -0.1412,
                'cv_mae' => 2.0891,
                'cv_rmse' => 2.6922,
            ],

            'mini' => [
                'name' => 'BaselineSemanal',
                'selection_score' => 0.0245,
                'test_mae' => 0.0000,
                'test_rmse' => 0.0000,
                'test_smape' => 0.0000,
                'test_r2' => 1.0000,
                'cv_mae' => 0.0612,
                'cv_rmse' => 0.2439,
            ],

            'small' => [
                'name' => 'RandomForest',
                'selection_score' => 1.3035,
                'test_mae' => 1.3251,
                'test_rmse' => 1.6997,
                'test_smape' => 76.4711,
                'test_r2' => 0.0655,
                'cv_mae' => 1.2711,
                'cv_rmse' => 1.5874,
            ],

            'total_units' => [
                'name' => 'RandomForest',
                'selection_score' => 3.4489,
                'test_mae' => $mae,
                'test_rmse' => 4.7997,
                'test_smape' => 60.1781,
                'test_r2' => 0.0363,
                'cv_mae' => 3.0430,
                'cv_rmse' => 3.8350,
            ],
        ],

        'summary' => [
            'historical_total_units' => 2056,
            'forecast_total_units' => 47,
            'forecast_daily_average' => 6.71,
            'highest_demand_date' => '2026-02-08',
            'highest_demand_day' => 'Domingo',
            'highest_demand_units' => 9,
            'highest_demand_size' => 'medium',
        ],

        'recommendations' => [
            'El día con mayor demanda estimada es Domingo 2026-02-08, con aproximadamente 9 pizzas.',
            'El tamaño con mayor demanda proyectada es Mediana.',
            'El promedio estimado para los próximos 7 días es de 6.71 pizzas diarias.',
            'Conviene preparar ingredientes, masa y personal antes de los días que superen el promedio pronosticado.',
        ],

        'predictions' => [
            [
                'date' => '2026-02-08',
                'mini' => 0,
                'small' => 3,
                'medium' => 3,
                'family' => 3,
                'total_units' => 9,
                'day_of_week' => 'Domingo',
            ],
            [
                'date' => '2026-02-09',
                'mini' => 0,
                'small' => 1,
                'medium' => 2,
                'family' => 1,
                'total_units' => 4,
                'day_of_week' => 'Lunes',
            ],
            [
                'date' => '2026-02-10',
                'mini' => 0,
                'small' => 2,
                'medium' => 2,
                'family' => 1,
                'total_units' => 5,
                'day_of_week' => 'Martes',
            ],
            [
                'date' => '2026-02-11',
                'mini' => 0,
                'small' => 2,
                'medium' => 2,
                'family' => 1,
                'total_units' => 5,
                'day_of_week' => 'Miércoles',
            ],
            [
                'date' => '2026-02-12',
                'mini' => 0,
                'small' => 2,
                'medium' => 2,
                'family' => 2,
                'total_units' => 6,
                'day_of_week' => 'Jueves',
            ],
            [
                'date' => '2026-02-13',
                'mini' => 0,
                'small' => 3,
                'medium' => 4,
                'family' => 2,
                'total_units' => 9,
                'day_of_week' => 'Viernes',
            ],
            [
                'date' => '2026-02-14',
                'mini' => 0,
                'small' => 3,
                'medium' => 3,
                'family' => 3,
                'total_units' => 9,
                'day_of_week' => 'Sábado',
            ],
        ],
    ];
}

it(
    'requires authentication to access machine learning admin endpoints',
    function (): void {
        /** @var TestCase $this */

        $this
            ->getJson(
                '/api/v1/admin/machine-learning/latest',
            )
            ->assertUnauthorized();
    },
);

it(
    'forbids customers from importing forecasts',
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
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                cheofPizzaForecastPayload(),
            )
            ->assertForbidden();
    },
);

it(
    'allows an administrator to import the colab forecast',
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
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                cheofPizzaForecastPayload(),
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.model.algorithm',
                'RandomForest',
            )
            ->assertJsonPath(
                'data.model.is_active',
                true,
            )
            ->assertJsonPath(
                'data.summary.forecast_total_units',
                47,
            )
            ->assertJsonCount(
                7,
                'data.predictions',
            );

        $this->assertDatabaseCount(
            'ml_model_runs',
            1,
        );

        $this->assertDatabaseCount(
            'ml_daily_predictions',
            7,
        );

        $this->assertDatabaseHas(
            'ml_model_runs',
            [
                'algorithm' => 'RandomForest',
                'target' => 'total_units',
                'is_active' => true,
            ],
        );

        $this->assertDatabaseHas(
            'ml_daily_predictions',
            [
                'prediction_date' => '2026-02-08',
                'mini_pizzas' => 0,
                'small_pizzas' => 3,
                'medium_pizzas' => 3,
                'family_pizzas' => 3,
                'total_pizzas' => 9,
            ],
        );
    },
);

it(
    'does not duplicate the same imported json',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $payload = cheofPizzaForecastPayload();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $payload,
            )
            ->assertCreated();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $payload,
            )
            ->assertCreated();

        $this->assertDatabaseCount(
            'ml_model_runs',
            1,
        );

        $this->assertDatabaseCount(
            'ml_daily_predictions',
            7,
        );
    },
);

it(
    'rejects totals that do not match the sum of pizza sizes',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $payload = cheofPizzaForecastPayload();

        $payload['predictions'][0]['total_units'] = 99;

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $payload,
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'predictions.0.total_units',
            ]);

        $this->assertDatabaseCount(
            'ml_model_runs',
            0,
        );
    },
);

it(
    'returns the active forecast',
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
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                cheofPizzaForecastPayload(),
            )
            ->assertCreated();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/latest',
            )
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
                'data.model.is_active',
                true,
            )
            ->assertJsonPath(
                'data.summary.forecast_total_units',
                47,
            )
            ->assertJsonCount(
                7,
                'data.predictions',
            );
    },
);

it(
    'returns machine learning run history',
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
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                cheofPizzaForecastPayload(),
            )
            ->assertCreated();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/history',
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonCount(
                1,
                'data',
            );
    },
);

it(
    'returns a machine learning run by uuid',
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
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                cheofPizzaForecastPayload(),
            )
            ->assertCreated();

        $run = MlModelRun::query()->firstOrFail();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                "/api/v1/admin/machine-learning/runs/{$run->uuid}",
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.uuid',
                $run->uuid,
            )
            ->assertJsonPath(
                'data.model.algorithm',
                'RandomForest',
            )
            ->assertJsonCount(
                7,
                'data.predictions',
            );
    },
);

it(
    'keeps the existing active model when a new model has a worse mae',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $firstPayload = cheofPizzaForecastPayload(
            mae: 3.0,
            generatedAt: '2026-07-29T04:02:22+00:00',
        );

        $worsePayload = cheofPizzaForecastPayload(
            mae: 4.5,
            generatedAt: '2026-08-29T04:02:22+00:00',
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $firstPayload,
            )
            ->assertCreated();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $worsePayload,
            )
            ->assertCreated();

        expect(
            MlModelRun::query()
                ->where('is_active', true)
                ->value('mae'),
        )->toBe('3.0000');

        expect(
            MlModelRun::query()
                ->where('is_active', false)
                ->value('mae'),
        )->toBe('4.5000');

        expect(
            MlModelRun::query()
                ->where('is_active', true)
                ->count(),
        )->toBe(1);
    },
);

it(
    'activates a new model when its mae improves',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $firstPayload = cheofPizzaForecastPayload(
            mae: 4.0,
            generatedAt: '2026-07-29T04:02:22+00:00',
        );

        $betterPayload = cheofPizzaForecastPayload(
            mae: 2.9,
            generatedAt: '2026-08-29T04:02:22+00:00',
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $firstPayload,
            )
            ->assertCreated();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $betterPayload,
            )
            ->assertCreated();

        expect(
            MlModelRun::query()
                ->where('is_active', true)
                ->value('mae'),
        )->toBe('2.9000');

        expect(
            MlModelRun::query()
                ->where('is_active', false)
                ->value('mae'),
        )->toBe('4.0000');

        expect(
            MlModelRun::query()
                ->where('is_active', true)
                ->count(),
        )->toBe(1);
    },
);

it(
    'rejects a prediction date that is not after the training period',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $payload = cheofPizzaForecastPayload();

        $payload['predictions'][0]['date'] = '2026-02-07';

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $payload,
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'predictions.0.date',
            ]);

        $this->assertDatabaseCount(
            'ml_model_runs',
            0,
        );
    },
);

it(
    'rejects a forecast days value that differs from prediction count',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $payload = cheofPizzaForecastPayload();

        $payload['forecast_days'] = 6;

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/import',
                $payload,
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'forecast_days',
            ]);

        $this->assertDatabaseCount(
            'ml_model_runs',
            0,
        );
    },
);
