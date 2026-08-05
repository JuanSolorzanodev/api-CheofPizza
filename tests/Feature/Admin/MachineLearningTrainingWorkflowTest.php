<?php

declare(strict_types=1);

use App\Contracts\MachineLearning\MachineLearningClientContract;
use App\Exceptions\MachineLearningServiceException;
use App\Models\MlDailyFeature;
use App\Models\MlTrainingRun;
use App\Models\User;
use App\Services\MachineLearning\TrainingWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function createWorkflowFeature(
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
 * @return array<string, mixed>
 */
function workflowArtifactResponse(): array
{
    return [
        'built' => true,

        'artifact_id' => 'candidate-20260804T150000Z-abcd1234',

        'version' => 'sales-2026.08.04-abcd1234',

        'algorithm' => 'random_forest',

        'algorithm_label' => 'Random Forest',

        'schema_version' => '1.0',

        'trained_from' => '2026-06-15',

        'trained_until' => '2026-07-15',

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

                'mae' => 0.4,

                'rmse' => 0.6,
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

it(
    'persiste un candidato construido por fastapi',
    function (): void {
        $admin = User::factory()
            ->admin()
            ->create();

        foreach (
            range(
                0,
                44,
            ) as $index
        ) {
            createWorkflowFeature(
                now()
                    ->subDays(
                        44 - $index,
                    )
                    ->toDateString(),
            );
        }

        /** @var MachineLearningClientContract&MockInterface $client */
        $client = Mockery::mock(
            MachineLearningClientContract::class,
        );

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
                workflowArtifactResponse(),
            );

        app()->instance(
            MachineLearningClientContract::class,
            $client,
        );

        $service = app(
            TrainingWorkflowService::class,
        );

        $run = $service->build(
            options: [
                'limit' => 365,

                'include_empty_days' => true,
            ],
            admin: $admin,
        );

        expect($run)
            ->toBeInstanceOf(
                MlTrainingRun::class,
            )
            ->and($run->status)
            ->toBe(
                MlTrainingRun::STATUS_BUILT,
            )
            ->and($run->artifact_id)
            ->toBe(
                'candidate-20260804T150000Z-abcd1234',
            )
            ->and($run->algorithm)
            ->toBe(
                'random_forest',
            )
            ->and($run->training_records)
            ->toBe(31)
            ->and($run->is_active)
            ->toBeFalse();

        $persistedRun = MlTrainingRun::query()
            ->where(
                'uuid',
                $run->uuid,
            )
            ->where(
                'status',
                MlTrainingRun::STATUS_BUILT,
            )
            ->where(
                'artifact_id',
                'candidate-20260804T150000Z-abcd1234',
            )
            ->where(
                'created_by',
                $admin->id,
            )
            ->first();

        expect($persistedRun)
            ->not()
            ->toBeNull();
    },
);

it(
    'registra como fallida una construcción rechazada por fastapi',
    function (): void {
        $admin = User::factory()
            ->admin()
            ->create();

        createWorkflowFeature(
            '2026-08-01',
        );

        /** @var MachineLearningClientContract&MockInterface $client */
        $client = Mockery::mock(
            MachineLearningClientContract::class,
        );

        $client
            ->shouldReceive(
                'buildTrainingArtifact',
            )
            ->once()
            ->andThrow(
                new MachineLearningServiceException(
                    message: 'Dataset insuficiente.',

                    remoteStatus: 422,

                    remotePayload: [
                        'detail' => 'Dataset insuficiente.',
                    ],
                ),
            );

        app()->instance(
            MachineLearningClientContract::class,
            $client,
        );

        $service = app(
            TrainingWorkflowService::class,
        );

        expect(
            fn () => $service->build(
                options: [],
                admin: $admin,
            ),
        )->toThrow(
            MachineLearningServiceException::class,
            'Dataset insuficiente.',
        );

        $run = MlTrainingRun::query()
            ->sole();

        expect($run->status)
            ->toBe(
                MlTrainingRun::STATUS_FAILED,
            )
            ->and($run->remote_status)
            ->toBe(422)
            ->and($run->error_message)
            ->toBe(
                'Dataset insuficiente.',
            )
            ->and($run->failed_at)
            ->not()
            ->toBeNull();
    },
);

it(
    'previsualiza el entrenamiento sin persistir una ejecución',
    function (): void {
        createWorkflowFeature(
            '2026-08-01',
        );

        /** @var MachineLearningClientContract&MockInterface $client */
        $client = Mockery::mock(
            MachineLearningClientContract::class,
        );

        $client
            ->shouldReceive(
                'previewTraining',
            )
            ->once()
            ->andReturn([
                'trained' => false,

                'winner' => null,

                'warnings' => [
                    'Datos insuficientes.',
                ],
            ]);

        app()->instance(
            MachineLearningClientContract::class,
            $client,
        );

        $service = app(
            TrainingWorkflowService::class,
        );

        $result = $service->preview();

        expect($result)
            ->toHaveKeys([
                'dataset',
                'preview',
            ])
            ->and(
                $result[
                    'preview'
                ][
                    'trained'
                ],
            )
            ->toBeFalse();

        expect(
            MlTrainingRun::query()->count(),
        )->toBe(0);
    },
);
