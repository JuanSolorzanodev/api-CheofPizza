<?php

declare(strict_types=1);

use App\Contracts\MachineLearning\MachineLearningClientContract;
use App\Models\MlDailyFeature;
use App\Models\MlTrainingRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Crea una fila consolidada utilizable por el dataset
 * de entrenamiento.
 */
function trainingHttpFeature(
    string $date,
    int $pizzas = 10,
): MlDailyFeature {
    return MlDailyFeature::query()->create([
        'date' => $date,

        'total_pizzas_sold' => $pizzas,

        'mini_sales' => 1,

        'small_sales' => 2,

        'medium_sales' => max(
            0,
            $pizzas - 6,
        ),

        'family_sales' => 2,

        'giant_sales' => 1,

        'basic_sales' => 4,

        'special_sales' => max(
            0,
            $pizzas - 4,
        ),

        'promotion_sales' => 2,

        'regular_sales' => max(
            0,
            $pizzas - 2,
        ),

        'delivered_orders' => 5,

        'cancelled_orders' => 0,

        'net_sales' => $pizzas * 10,

        'pickup_orders' => 2,

        'delivery_orders' => 3,

        'consolidated_at' => now(),

        'source' => 'laravel_sales',
    ]);
}

/**
 * Crea suficientes registros para que el dataset sea maduro.
 */
function seedTrainingHttpFeatures(
    int $records = 45,
): void {
    foreach (
        range(
            0,
            $records - 1,
        ) as $index
    ) {
        trainingHttpFeature(
            now()
                ->subDays(
                    ($records - 1) - $index,
                )
                ->toDateString(),

            10 + ($index % 5),
        );
    }
}

/**
 * @return array<string, mixed>
 */
function trainingHttpPreviewResponse(): array
{
    return [
        'trained' => true,

        'schema_version' => '1.0',

        'received_records' => 45,

        'training_records' => 31,

        'folds' => 5,

        'trained_from' => now()
            ->subDays(44)
            ->toDateString(),

        'trained_until' => now()
            ->toDateString(),

        'targets' => [
            'mini_sales',
            'small_sales',
            'medium_sales',
            'family_sales',
            'giant_sales',
        ],

        'derived_targets' => [
            'total_pizzas_sold',
        ],

        'features' => [
            'day_of_week',
            'week_of_year',
            'month',
            'day_of_month',
            'is_weekend',
            'total_lag_1',
            'total_lag_7',
            'total_rolling_mean_7',
            'total_rolling_mean_14',
        ],

        'candidates' => [
            [
                'algorithm' => 'mean_baseline',

                'algorithm_label' => 'Mean Baseline',

                'mean_mae' => 1.8,

                'mean_rmse' => 2.2,
            ],

            [
                'algorithm' => 'ridge',

                'algorithm_label' => 'Ridge Regression',

                'mean_mae' => 1.1,

                'mean_rmse' => 1.5,
            ],

            [
                'algorithm' => 'random_forest',

                'algorithm_label' => 'Random Forest',

                'mean_mae' => 0.8,

                'mean_rmse' => 1.1,
            ],
        ],

        'winner' => [
            'algorithm' => 'random_forest',

            'algorithm_label' => 'Random Forest',

            'mean_mae' => 0.8,

            'mean_rmse' => 1.1,
        ],

        'warnings' => [],

        'message' => 'Evaluación completada correctamente.',
    ];
}

/**
 * @return array<string, mixed>
 */
function trainingHttpBuildResponse(): array
{
    return [
        'built' => true,

        'artifact_id' => 'candidate-20260804T150000Z-abcd1234',

        'version' => 'sales-2026.08.04-abcd1234',

        'algorithm' => 'random_forest',

        'algorithm_label' => 'Random Forest',

        'schema_version' => '1.0',

        'trained_from' => now()
            ->subDays(44)
            ->toDateString(),

        'trained_until' => now()
            ->toDateString(),

        'received_records' => 45,

        'training_records' => 31,

        'targets' => [
            'mini_sales',
            'small_sales',
            'medium_sales',
            'family_sales',
            'giant_sales',
        ],

        'derived_targets' => [
            'total_pizzas_sold',
        ],

        'features' => [
            'day_of_week',
            'week_of_year',
            'month',
            'day_of_month',
            'is_weekend',
            'total_lag_1',
            'total_lag_7',
            'total_rolling_mean_7',
            'total_rolling_mean_14',
        ],

        'metrics' => [
            [
                'target' => 'mini_sales',

                'algorithm' => 'random_forest',

                'mae' => 0.4,

                'rmse' => 0.6,
            ],

            [
                'target' => 'small_sales',

                'algorithm' => 'random_forest',

                'mae' => 0.7,

                'rmse' => 0.9,
            ],
        ],

        'mean_mae' => 0.8,

        'mean_rmse' => 1.1,

        'activated' => false,

        'created_at' => now()->toIso8601String(),

        'warnings' => [],

        'message' => 'Candidato construido correctamente.',
    ];
}

/**
 * @return array<string, mixed>
 */
function trainingHttpRegistryResponse(
    string $kind = 'legacy',
    string $artifactId = 'legacy-calendar-model',
): array {
    return [
        'registry_version' => '1.0',

        'active' => [
            'kind' => $kind,

            'artifact_id' => $artifactId,

            'version' => $kind === 'legacy'
                    ? 'legacy'
                    : 'sales-2026.08.04-abcd1234',

            'activated_at' => now()->toIso8601String(),
        ],

        'rollback_available' => $kind === 'historical',

        'rollback_depth' => $kind === 'historical'
                ? 1
                : 0,

        'history' => $kind === 'historical'
                ? [
                    [
                        'kind' => 'legacy',

                        'artifact_id' => 'legacy-calendar-model',

                        'version' => 'legacy',

                        'activated_at' => now()
                            ->subMinute()
                            ->toIso8601String(),
                    ],
                ]
                : [],

        'updated_at' => now()->toIso8601String(),
    ];
}

/**
 * @return array<string, mixed>
 */
function trainingHttpActivationResponse(
    string $artifactId,
): array {
    return [
        'activated' => true,

        'previous' => [
            'kind' => 'legacy',

            'artifact_id' => 'legacy-calendar-model',

            'version' => 'legacy',

            'activated_at' => now()
                ->subMinute()
                ->toIso8601String(),
        ],

        'active' => [
            'kind' => 'historical',

            'artifact_id' => $artifactId,

            'version' => 'sales-2026.08.04-abcd1234',

            'activated_at' => now()->toIso8601String(),
        ],

        'rollback_available' => true,

        'message' => 'El candidato fue activado correctamente.',
    ];
}

/**
 * @return array<string, mixed>
 */
function trainingHttpRollbackResponse(): array
{
    return [
        'rolled_back' => true,

        'previous' => [
            'kind' => 'historical',

            'artifact_id' => 'candidate-20260804T150000Z-abcd1234',

            'version' => 'sales-2026.08.04-abcd1234',

            'activated_at' => now()
                ->subMinute()
                ->toIso8601String(),
        ],

        'active' => [
            'kind' => 'legacy',

            'artifact_id' => 'legacy-calendar-model',

            'version' => 'legacy',

            'activated_at' => now()->toIso8601String(),
        ],

        'rollback_available' => false,

        'message' => 'El modelo anterior fue restaurado correctamente.',
    ];
}

/**
 * Registra un mock del cliente remoto en el contenedor.
 *
 * @param  callable(MockInterface): void  $configure
 */
function mockTrainingHttpClient(
    callable $configure,
): MachineLearningClientContract {
    /** @var MachineLearningClientContract&MockInterface $client */
    $client = \Mockery::mock(
        MachineLearningClientContract::class,
    );

    $configure(
        $client,
    );

    app()->instance(
        MachineLearningClientContract::class,
        $client,
    );

    return $client;
}

it(
    'requires authentication for training administration',
    function (): void {
        /** @var TestCase $this */
        $this
            ->getJson(
                '/api/v1/admin/machine-learning/training/runs',
            )
            ->assertUnauthorized();
    },
);

it(
    'forbids customers from accessing training administration',
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
                '/api/v1/admin/machine-learning/training/runs',
            )
            ->assertForbidden();
    },
);

it(
    'allows an administrator to inspect the remote model registry',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        mockTrainingHttpClient(
            static function (
                MockInterface $client,
            ): void {
                $client
                    ->shouldReceive(
                        'registry',
                    )
                    ->once()
                    ->andReturn(
                        trainingHttpRegistryResponse(),
                    );
            },
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/training/registry',
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.registry_version',
                '1.0',
            )
            ->assertJsonPath(
                'data.active.kind',
                'legacy',
            )
            ->assertJsonPath(
                'data.active.artifact_id',
                'legacy-calendar-model',
            )
            ->assertJsonPath(
                'data.rollback_available',
                false,
            );
    },
);

it(
    'lists persisted training runs',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        MlTrainingRun::query()->create([
            'uuid' => fake()->uuid(),

            'dataset_hash' => hash(
                'sha256',
                'first-dataset',
            ),

            'status' => MlTrainingRun::STATUS_BUILT,

            'schema_version' => '1.0',

            'artifact_id' => 'candidate-first',

            'version' => 'sales-first',

            'algorithm' => 'ridge',

            'algorithm_label' => 'Ridge Regression',

            'trained_from' => now()
                ->subDays(30)
                ->toDateString(),

            'trained_until' => now()
                ->toDateString(),

            'received_records' => 45,

            'training_records' => 31,

            'mean_mae' => 1.2,

            'mean_rmse' => 1.7,

            'is_active' => false,

            'built_at' => now(),

            'created_by' => $admin->id,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/machine-learning/training/runs',
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
            ->assertJsonPath(
                'data.0.status',
                MlTrainingRun::STATUS_BUILT,
            )
            ->assertJsonPath(
                'data.0.artifact.id',
                'candidate-first',
            )
            ->assertJsonPath(
                'data.0.artifact.algorithm',
                'ridge',
            );
    },
);

it(
    'shows a persisted training run by uuid',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $run = MlTrainingRun::query()->create([
            'uuid' => fake()->uuid(),

            'dataset_hash' => hash(
                'sha256',
                'show-dataset',
            ),

            'status' => MlTrainingRun::STATUS_BUILT,

            'schema_version' => '1.0',

            'artifact_id' => 'candidate-show',

            'version' => 'sales-show',

            'algorithm' => 'random_forest',

            'algorithm_label' => 'Random Forest',

            'received_records' => 45,

            'training_records' => 31,

            'mean_mae' => 0.8,

            'mean_rmse' => 1.1,

            'is_active' => false,

            'built_at' => now(),

            'created_by' => $admin->id,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                (
                    '/api/v1/admin/machine-learning/'
                    .'training/runs/'
                    .$run->uuid
                ),
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
                'data.artifact.id',
                'candidate-show',
            )
            ->assertJsonPath(
                'data.artifact.algorithm',
                'random_forest',
            );
    },
);

it(
    'previews training without persisting a run',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        seedTrainingHttpFeatures();

        mockTrainingHttpClient(
            static function (
                MockInterface $client,
            ): void {
                $client
                    ->shouldReceive(
                        'previewTraining',
                    )
                    ->once()
                    ->withArgs(
                        static fn (
                            array $dataset,
                        ): bool => $dataset[
                                'summary'
                            ][
                                'records'
                            ] === 45,
                    )
                    ->andReturn(
                        trainingHttpPreviewResponse(),
                    );
            },
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/training/preview',
                [
                    'limit' => 365,

                    'include_empty_days' => true,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.preview.trained',
                true,
            )
            ->assertJsonPath(
                'data.preview.winner.algorithm',
                'random_forest',
            )
            ->assertJsonPath(
                'data.preview.winner.mean_mae',
                0.8,
            );

        expect(
            MlTrainingRun::query()->count(),
        )->toBe(0);
    },
);

it(
    'builds and persists a candidate through the administrative endpoint',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        seedTrainingHttpFeatures();

        mockTrainingHttpClient(
            static function (
                MockInterface $client,
            ): void {
                $client
                    ->shouldReceive(
                        'buildTrainingArtifact',
                    )
                    ->once()
                    ->withArgs(
                        static fn (
                            array $dataset,
                        ): bool => $dataset[
                                'summary'
                            ][
                                'records'
                            ] === 45,
                    )
                    ->andReturn(
                        trainingHttpBuildResponse(),
                    );
            },
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/training/build',
                [
                    'limit' => 365,

                    'include_empty_days' => true,
                ],
            )
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.status',
                MlTrainingRun::STATUS_BUILT,
            )
            ->assertJsonPath(
                'data.artifact.id',
                'candidate-20260804T150000Z-abcd1234',
            )
            ->assertJsonPath(
                'data.artifact.algorithm',
                'random_forest',
            )
            ->assertJsonPath(
                'data.artifact.is_active',
                false,
            );

        $run = MlTrainingRun::query()
            ->sole();

        expect($run->status)
            ->toBe(
                MlTrainingRun::STATUS_BUILT,
            )
            ->and($run->artifact_id)
            ->toBe(
                'candidate-20260804T150000Z-abcd1234',
            )
            ->and($run->created_by)
            ->toBe(
                $admin->id,
            )
            ->and($run->is_active)
            ->toBeFalse();
    },
);

it(
    'activates a built candidate and synchronizes its local state',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $run = MlTrainingRun::query()->create([
            'uuid' => fake()->uuid(),

            'dataset_hash' => hash(
                'sha256',
                'activation-dataset',
            ),

            'status' => MlTrainingRun::STATUS_BUILT,

            'schema_version' => '1.0',

            'artifact_id' => 'candidate-20260804T150000Z-abcd1234',

            'version' => 'sales-2026.08.04-abcd1234',

            'algorithm' => 'random_forest',

            'algorithm_label' => 'Random Forest',

            'received_records' => 45,

            'training_records' => 31,

            'mean_mae' => 0.8,

            'mean_rmse' => 1.1,

            'is_active' => false,

            'built_at' => now(),

            'created_by' => $admin->id,
        ]);

        mockTrainingHttpClient(
            static function (
                MockInterface $client,
            ) use (
                $run,
            ): void {
                $client
                    ->shouldReceive(
                        'activateModel',
                    )
                    ->once()
                    ->with(
                        $run->artifact_id,
                    )
                    ->andReturn(
                        trainingHttpActivationResponse(
                            $run->artifact_id,
                        ),
                    );
            },
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                (
                    '/api/v1/admin/machine-learning/'
                    .'training/runs/'
                    .$run->uuid
                    .'/activate'
                ),
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.registry.activated',
                true,
            )
            ->assertJsonPath(
                'data.registry.active.kind',
                'historical',
            )
            ->assertJsonPath(
                'data.training_run.status',
                MlTrainingRun::STATUS_ACTIVATED,
            )
            ->assertJsonPath(
                'data.training_run.artifact.is_active',
                true,
            );

        $run->refresh();

        expect($run->status)
            ->toBe(
                MlTrainingRun::STATUS_ACTIVATED,
            )
            ->and($run->is_active)
            ->toBeTrue()
            ->and($run->activated_at)
            ->not()
            ->toBeNull();
    },
);

it(
    'rolls back to the legacy model and deactivates the local candidate',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $run = MlTrainingRun::query()->create([
            'uuid' => fake()->uuid(),

            'dataset_hash' => hash(
                'sha256',
                'rollback-dataset',
            ),

            'status' => MlTrainingRun::STATUS_ACTIVATED,

            'schema_version' => '1.0',

            'artifact_id' => 'candidate-20260804T150000Z-abcd1234',

            'version' => 'sales-2026.08.04-abcd1234',

            'algorithm' => 'random_forest',

            'algorithm_label' => 'Random Forest',

            'received_records' => 45,

            'training_records' => 31,

            'mean_mae' => 0.8,

            'mean_rmse' => 1.1,

            'is_active' => true,

            'built_at' => now()
                ->subMinute(),

            'activated_at' => now()
                ->subMinute(),

            'created_by' => $admin->id,
        ]);

        mockTrainingHttpClient(
            static function (
                MockInterface $client,
            ): void {
                $client
                    ->shouldReceive(
                        'rollbackModel',
                    )
                    ->once()
                    ->andReturn(
                        trainingHttpRollbackResponse(),
                    );
            },
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                '/api/v1/admin/machine-learning/training/rollback',
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'data.registry.rolled_back',
                true,
            )
            ->assertJsonPath(
                'data.registry.active.kind',
                'legacy',
            )
            ->assertJsonPath(
                'data.registry.active.artifact_id',
                'legacy-calendar-model',
            )
            ->assertJsonPath(
                'data.training_run',
                null,
            );

        $run->refresh();

        expect($run->status)
            ->toBe(
                MlTrainingRun::STATUS_ROLLED_BACK,
            )
            ->and($run->is_active)
            ->toBeFalse()
            ->and($run->rolled_back_at)
            ->not()
            ->toBeNull();
    },
);

it(
    'rejects activation of a training run without a built artifact',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $run = MlTrainingRun::query()->create([
            'uuid' => fake()->uuid(),

            'dataset_hash' => hash(
                'sha256',
                'failed-dataset',
            ),

            'status' => MlTrainingRun::STATUS_FAILED,

            'schema_version' => '1.0',

            'artifact_id' => null,

            'received_records' => 1,

            'training_records' => 0,

            'is_active' => false,

            'failed_at' => now(),

            'error_message' => 'Dataset insuficiente.',

            'created_by' => $admin->id,
        ]);

        mockTrainingHttpClient(
            static function (
                MockInterface $client,
            ): void {
                $client
                    ->shouldNotReceive(
                        'activateModel',
                    );
            },
        );

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->postJson(
                (
                    '/api/v1/admin/machine-learning/'
                    .'training/runs/'
                    .$run->uuid
                    .'/activate'
                ),
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'success',
                false,
            )
            ->assertJsonPath(
                'code',
                'ML_TRAINING_RUN_NOT_ACTIVATABLE',
            );

        $run->refresh();

        expect($run->status)
            ->toBe(
                MlTrainingRun::STATUS_FAILED,
            )
            ->and($run->is_active)
            ->toBeFalse();
    },
);
