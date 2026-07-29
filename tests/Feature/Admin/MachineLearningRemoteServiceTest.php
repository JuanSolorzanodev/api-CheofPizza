<?php

declare(strict_types=1);

use App\Models\MlDailyPrediction;
use App\Models\MlModelRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Respuesta simulada del endpoint remoto:
 *
 * POST /api/v1/predict
 *
 * @return array<string, mixed>
 */
function cheofPizzaRemoteForecastPayload(): array
{
    return [
        'generated_at' =>
            '2026-07-29T20:34:23.000000Z',

        'forecast_from' =>
            '2026-07-30',

        'forecast_until' =>
            '2026-08-05',

        'forecast_days' =>
            7,

        'model' => [
            'type' =>
                'calendar_demand_model',

            'version' =>
                '1.0.0',

            'trained_from' =>
                '2025-03-22',

            'trained_until' =>
                '2026-02-07',

            'training_records' =>
                323,

            'features' => [
                'year',
                'month',
                'day_of_month',
                'day_of_week',
                'week_of_year',
                'day_of_year',
                'is_weekend',
                'is_month_start',
                'is_month_end',
                'dow_sin',
                'dow_cos',
                'month_sin',
                'month_cos',
                'year_day_sin',
                'year_day_cos',
            ],

            'metrics' => [
                [
                    'target' =>
                        'family',

                    'algorithm' =>
                        'CalendarBaseline',

                    'selection_score' =>
                        1.6159975659942036,

                    'mae' =>
                        1.7250844594594594,

                    'rmse' =>
                        2.536779760292151,

                    'smape' =>
                        110.53646213698123,

                    'r2' =>
                        0.04797232065859458,

                    'cv_mae' =>
                        1.45236722579632,

                    'cv_rmse' =>
                        1.7969227503704008,
                ],

                [
                    'target' =>
                        'medium',

                    'algorithm' =>
                        'CalendarBaseline',

                    'selection_score' =>
                        1.9359987460901895,

                    'mae' =>
                        1.8551520270270272,

                    'rmse' =>
                        2.381042637815869,

                    'smape' =>
                        91.10517102306893,

                    'r2' =>
                        0.09048585615988058,

                    'cv_mae' =>
                        2.057268824684933,

                    'cv_rmse' =>
                        2.581413300152653,
                ],

                [
                    'target' =>
                        'mini',

                    'algorithm' =>
                        'CalendarBaseline',

                    'selection_score' =>
                        0.04950066814136617,

                    'mae' =>
                        0.03505067567567568,

                    'rmse' =>
                        0.044691745119334304,

                    'smape' =>
                        200.0,

                    'r2' =>
                        0.0,

                    'cv_mae' =>
                        0.07117565683990192,

                    'cv_rmse' =>
                        0.19495590924236939,
                ],

                [
                    'target' =>
                        'small',

                    'algorithm' =>
                        'CalendarBaseline',

                    'selection_score' =>
                        1.2423255574714036,

                    'mae' =>
                        1.2778716216216217,

                    'rmse' =>
                        1.6987365325907693,

                    'smape' =>
                        76.58459356009381,

                    'r2' =>
                        0.05099550492804039,

                    'cv_mae' =>
                        1.1890064612460767,

                    'cv_rmse' =>
                        1.48688974141096,
                ],

                [
                    'target' =>
                        'total_units',

                    'algorithm' =>
                        'CalendarBaseline',

                    'selection_score' =>
                        3.276943052260596,

                    'mae' =>
                        3.4818412162162167,

                    'rmse' =>
                        4.502143947924203,

                    'smape' =>
                        59.5071158873143,

                    'r2' =>
                        0.1459939379979236,

                    'cv_mae' =>
                        2.969595806327165,

                    'cv_rmse' =>
                        3.732998766324431,
                ],
            ],
        ],

        'summary' => [
            'forecast_total_units' =>
                44,

            'forecast_daily_average' =>
                6.29,

            'highest_demand_date' =>
                '2026-08-01',

            'highest_demand_day' =>
                'Sábado',

            'highest_demand_units' =>
                10,

            'highest_demand_size' =>
                'medium',
        ],

        'predictions' => [
            [
                'date' =>
                    '2026-07-30',

                'day_of_week' =>
                    'Jueves',

                'total_units' =>
                    7,

                'sizes' => [
                    'mini' => 0,
                    'small' => 2,
                    'medium' => 3,
                    'family' => 2,
                ],
            ],

            [
                'date' =>
                    '2026-07-31',

                'day_of_week' =>
                    'Viernes',

                'total_units' =>
                    8,

                'sizes' => [
                    'mini' => 0,
                    'small' => 2,
                    'medium' => 4,
                    'family' => 2,
                ],
            ],

            [
                'date' =>
                    '2026-08-01',

                'day_of_week' =>
                    'Sábado',

                'total_units' =>
                    10,

                'sizes' => [
                    'mini' => 0,
                    'small' => 3,
                    'medium' => 4,
                    'family' => 3,
                ],
            ],

            [
                'date' =>
                    '2026-08-02',

                'day_of_week' =>
                    'Domingo',

                'total_units' =>
                    8,

                'sizes' => [
                    'mini' => 0,
                    'small' => 2,
                    'medium' => 4,
                    'family' => 2,
                ],
            ],

            [
                'date' =>
                    '2026-08-03',

                'day_of_week' =>
                    'Lunes',

                'total_units' =>
                    4,

                'sizes' => [
                    'mini' => 0,
                    'small' => 1,
                    'medium' => 2,
                    'family' => 1,
                ],
            ],

            [
                'date' =>
                    '2026-08-04',

                'day_of_week' =>
                    'Martes',

                'total_units' =>
                    4,

                'sizes' => [
                    'mini' => 0,
                    'small' => 1,
                    'medium' => 2,
                    'family' => 1,
                ],
            ],

            [
                'date' =>
                    '2026-08-05',

                'day_of_week' =>
                    'Miércoles',

                'total_units' =>
                    3,

                'sizes' => [
                    'mini' => 0,
                    'small' => 1,
                    'medium' => 1,
                    'family' => 1,
                ],
            ],
        ],

        'limitations' => [
            'uses_recent_sales' =>
                false,

            'uses_flavor_data' =>
                false,

            'uses_hourly_data' =>
                false,

            'message' =>
                'Modelo inicial basado únicamente en variables de calendario y ventas históricas por tamaño.',
        ],
    ];
}

/**
 * Respuesta simulada del endpoint remoto:
 *
 * GET /api/v1/model
 *
 * @return array<string, mixed>
 */
function cheofPizzaRemoteModelPayload(): array
{
    $forecast = cheofPizzaRemoteForecastPayload();

    /** @var array<string, mixed> $model */
    $model = $forecast['model'];

    return $model;
}

beforeEach(function (): void {
    config([
        'services.machine_learning.base_url' =>
            'https://cheofpizza-ml.test',

        'services.machine_learning.api_key' =>
            'testing-ml-secret',

        'services.machine_learning.timeout' =>
            20,

        'services.machine_learning.connect_timeout' =>
            5,
    ]);
});

it(
    'requires authentication to access the remote model endpoint',
    function (): void {
        /** @var TestCase $this */

        $this
            ->getJson(
                '/api/v1/admin/machine-learning/service/model'
            )
            ->assertUnauthorized();
    }
);

it(
    'forbids customers from accessing the remote model endpoint',
    function (): void {
        /** @var TestCase $this */

        $customer = User::factory()
            ->customer()
            ->create();

        $this
            ->actingAs(
                $customer,
                'sanctum'
            )
            ->getJson(
                '/api/v1/admin/machine-learning/service/model'
            )
            ->assertForbidden();
    }
);

it(
    'allows an administrator to inspect the remote model',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/model' =>
                Http::response(
                    cheofPizzaRemoteModelPayload(),
                    200
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                '/api/v1/admin/machine-learning/service/model'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'message',
                'Modelo remoto recuperado correctamente.'
            )
            ->assertJsonPath(
                'data.type',
                'calendar_demand_model'
            )
            ->assertJsonPath(
                'data.version',
                '1.0.0'
            )
            ->assertJsonPath(
                'data.training_records',
                323
            );

        Http::assertSent(
            static function (
                Request $request
            ): bool {
                return $request->method() === 'GET'
                    && $request->url()
                    === 'https://cheofpizza-ml.test/api/v1/model'
                    && $request->hasHeader(
                        'X-ML-API-Key',
                        'testing-ml-secret'
                    )
                    && $request->hasHeader(
                        'Accept',
                        'application/json'
                    );
            }
        );
    }
);

it(
    'generates a remote forecast preview without persisting it',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/predict' =>
                Http::response(
                    cheofPizzaRemoteForecastPayload(),
                    200
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/preview',
                [
                    'start_date' =>
                        '2026-07-30',

                    'days' =>
                        7,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.forecast_days',
                7
            )
            ->assertJsonPath(
                'data.summary.forecast_total_units',
                44
            )
            ->assertJsonCount(
                7,
                'data.predictions'
            );

        $this->assertDatabaseCount(
            'ml_model_runs',
            0
        );

        $this->assertDatabaseCount(
            'ml_daily_predictions',
            0
        );

        Http::assertSent(
            static function (
                Request $request
            ): bool {
                return $request->method() === 'POST'
                    && $request->url()
                    === 'https://cheofpizza-ml.test/api/v1/predict'
                    && $request->data() === [
                        'start_date' =>
                            '2026-07-30',

                        'days' =>
                            7,
                    ];
            }
        );
    }
);

it(
    'uses seven days by default when preview days are omitted',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/predict' =>
                Http::response(
                    cheofPizzaRemoteForecastPayload(),
                    200
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/preview',
                [
                    'start_date' =>
                        '2026-07-30',
                ]
            )
            ->assertOk();

        Http::assertSent(
            static function (
                Request $request
            ): bool {
                return $request->data() === [
                    'start_date' =>
                        '2026-07-30',

                    'days' =>
                        7,
                ];
            }
        );
    }
);

it(
    'validates the remote forecast request before calling fastapi',
    function (): void {
        /** @var TestCase $this */

        Http::fake();

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/preview',
                [
                    'start_date' =>
                        '29-07-2026',

                    'days' =>
                        40,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'start_date',
                'days',
            ]);

        Http::assertNothingSent();
    }
);

it(
    'generates and persists a remote forecast',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/predict' =>
                Http::response(
                    cheofPizzaRemoteForecastPayload(),
                    200
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/generate',
                [
                    'start_date' =>
                        '2026-07-30',

                    'days' =>
                        7,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'message',
                'Pronóstico generado y guardado correctamente.'
            )
            ->assertJsonPath(
                'data.source',
                MlModelRun::SOURCE_ML_SERVICE
            )
            ->assertJsonPath(
                'data.status',
                MlModelRun::STATUS_COMPLETED
            )
            ->assertJsonPath(
                'data.model.algorithm',
                'CalendarBaseline'
            )
            ->assertJsonPath(
                'data.model.version',
                '1.0.0'
            )
            ->assertJsonPath(
                'data.model.is_active',
                true
            )
            ->assertJsonPath(
                'data.summary.forecast_total_units',
                44
            )
            ->assertJsonCount(
                7,
                'data.predictions'
            );

        $this->assertDatabaseCount(
            'ml_model_runs',
            1
        );

        $this->assertDatabaseCount(
            'ml_daily_predictions',
            7
        );

        $this->assertDatabaseHas(
            'ml_model_runs',
            [
                'source' =>
                    MlModelRun::SOURCE_ML_SERVICE,

                'status' =>
                    MlModelRun::STATUS_COMPLETED,

                'algorithm' =>
                    'CalendarBaseline',

                'target' =>
                    MlModelRun::TARGET_TOTAL_UNITS,

                'version' =>
                    '1.0.0',

                'training_records' =>
                    323,

                'forecast_days' =>
                    7,

                'forecast_from' =>
                    '2026-07-30',

                'forecast_until' =>
                    '2026-08-05',

                'is_active' =>
                    true,

                'created_by' =>
                    $admin->id,
            ]
        );

        $this->assertDatabaseHas(
            'ml_daily_predictions',
            [
                'prediction_date' =>
                    '2026-08-01',

                'mini_pizzas' =>
                    0,

                'small_pizzas' =>
                    3,

                'medium_pizzas' =>
                    4,

                'family_pizzas' =>
                    3,

                'total_pizzas' =>
                    10,
            ]
        );
    }
);

it(
    'does not duplicate an identical remote forecast',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/predict' =>
                Http::response(
                    cheofPizzaRemoteForecastPayload(),
                    200
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $payload = [
            'start_date' =>
                '2026-07-30',

            'days' =>
                7,
        ];

        $firstResponse = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/generate',
                $payload
            )
            ->assertCreated();

        $secondResponse = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/generate',
                $payload
            )
            ->assertCreated();

        expect(
            $firstResponse->json('data.id')
        )->toBe(
            $secondResponse->json('data.id')
        );

        expect(
            $firstResponse->json('data.uuid')
        )->toBe(
            $secondResponse->json('data.uuid')
        );

        $this->assertDatabaseCount(
            'ml_model_runs',
            1
        );

        $this->assertDatabaseCount(
            'ml_daily_predictions',
            7
        );

        expect(
            MlModelRun::query()
                ->where(
                    'source',
                    MlModelRun::SOURCE_ML_SERVICE
                )
                ->count()
        )->toBe(1);

        expect(
            MlDailyPrediction::query()
                ->whereHas(
                    'modelRun',
                    static fn ($query) =>
                        $query->where(
                            'source',
                            MlModelRun::SOURCE_ML_SERVICE
                        )
                )
                ->count()
        )->toBe(7);
    }
);

it(
    'converts a remote authentication failure into a gateway error',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/model' =>
                Http::response(
                    [
                        'detail' =>
                            'Invalid API key.',
                    ],
                    401
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                '/api/v1/admin/machine-learning/service/model'
            )
            ->assertStatus(502)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'message',
                'Invalid API key.'
            )
            ->assertJsonPath(
                'code',
                'ML_SERVICE_UNAVAILABLE'
            );
    }
);

it(
    'returns a validation error when fastapi rejects the prediction',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/predict' =>
                Http::response(
                    [
                        'detail' =>
                            'The prediction period is invalid.',
                    ],
                    422
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/preview',
                [
                    'start_date' =>
                        '2026-07-30',

                    'days' =>
                        7,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'message',
                'The prediction period is invalid.'
            )
            ->assertJsonPath(
                'code',
                'ML_SERVICE_VALIDATION_FAILED'
            );
    }
);

it(
    'rejects an incomplete successful response from fastapi',
    function (): void {
        /** @var TestCase $this */

        Http::fake([
            'https://cheofpizza-ml.test/api/v1/predict' =>
                Http::response(
                    [
                        'generated_at' =>
                            '2026-07-29T20:34:23.000000Z',
                    ],
                    200
                ),
        ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/v1/admin/machine-learning/generate',
                [
                    'start_date' =>
                        '2026-07-30',

                    'days' =>
                        7,
                ]
            )
            ->assertStatus(503)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'code',
                'ML_SERVICE_UNAVAILABLE'
            );

        $this->assertDatabaseCount(
            'ml_model_runs',
            0
        );

        $this->assertDatabaseCount(
            'ml_daily_predictions',
            0
        );
    }
);
