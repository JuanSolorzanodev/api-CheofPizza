<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Contracts\MachineLearning\MachineLearningClientContract;
use App\Exceptions\MachineLearningServiceException;
use App\Models\MlTrainingRun;
use App\Models\User;
use App\Services\MachineLearning\Dataset\MlTrainingDatasetService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

final class TrainingWorkflowService
{
    public function __construct(
        private readonly MlTrainingDatasetService $datasetService,
        private readonly MachineLearningClientContract $client,
    ) {}

    /**
     * Consulta el registro persistente del servicio FastAPI.
     *
     * @return array<string, mixed>
     */
    public function registry(): array
    {
        return $this->client->registry();
    }

    /**
     * Construye el dataset consolidado y solicita una
     * comparación remota sin persistir una ejecución.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     dataset: array<string, mixed>,
     *     preview: array<string, mixed>
     * }
     */
    public function preview(
        array $options = [],
    ): array {
        $dataset = $this->buildDataset(
            $options,
        );

        $preview = $this->client
            ->previewTraining(
                $dataset,
            );

        return [
            'dataset' => [
                'schema_version' => $dataset['schema_version'],

                'generated_at' => $dataset['generated_at'],

                'timezone' => $dataset['timezone'],

                'maturity' => $dataset['maturity'],

                'summary' => $dataset['summary'],
            ],

            'preview' => $preview,
        ];
    }

    /**
     * Construye un artefacto candidato en FastAPI
     * y persiste su trazabilidad en Laravel.
     *
     * @param  array<string, mixed>  $options
     */
    public function build(
        array $options,
        User $admin,
    ): MlTrainingRun {
        $dataset = $this->buildDataset(
            $options,
        );

        $run = MlTrainingRun::query()->create([
            'uuid' => (string) Str::uuid(),

            'dataset_hash' => $this->datasetHash(
                $dataset,
            ),

            'status' => MlTrainingRun::STATUS_PROCESSING,

            'schema_version' => (string) $dataset[
                    'schema_version'
                ],

            'received_records' => (int) (
                $dataset[
                    'summary'
                ][
                    'records'
                ] ?? 0
            ),

            'request_options' => $this->normalizeOptions(
                $options,
            ),

            'dataset_summary' => $dataset[
                    'summary'
                ],

            'created_by' => $admin->getKey(),
        ]);

        try {
            $remote = $this->client
                ->buildTrainingArtifact(
                    $dataset,
                );

            return DB::transaction(
                static function () use (
                    $run,
                    $remote,
                ): MlTrainingRun {
                    $run->forceFill([
                        'status' => MlTrainingRun::STATUS_BUILT,

                        'artifact_id' => (string) $remote[
                                'artifact_id'
                            ],

                        'version' => (string) $remote[
                                'version'
                            ],

                        'algorithm' => (string) $remote[
                                'algorithm'
                            ],

                        'algorithm_label' => (string) $remote[
                                'algorithm_label'
                            ],

                        'trained_from' => $remote[
                                'trained_from'
                            ],

                        'trained_until' => $remote[
                                'trained_until'
                            ],

                        'received_records' => (int) $remote[
                                'received_records'
                            ],

                        'training_records' => (int) $remote[
                                'training_records'
                            ],

                        'mean_mae' => (float) $remote[
                                'mean_mae'
                            ],

                        'mean_rmse' => (float) $remote[
                                'mean_rmse'
                            ],

                        'targets' => $remote[
                                'targets'
                            ],

                        'derived_targets' => $remote[
                                'derived_targets'
                            ],

                        'features' => $remote[
                                'features'
                            ],

                        'metrics' => $remote[
                                'metrics'
                            ],

                        'warnings' => $remote[
                                'warnings'
                            ] ?? [],

                        'remote_response' => [
                            'build' => $remote,
                        ],

                        'built_at' => now(),

                        'error_message' => null,

                        'remote_status' => null,

                        'failed_at' => null,
                    ])->save();

                    return $run->fresh([
                        'creator.role',
                    ]);
                },
            );
        } catch (
            MachineLearningServiceException $exception
        ) {
            $this->markFailed(
                run: $run,
                message: $exception->getMessage(),

                remoteStatus: $exception->remoteStatus(),

                remotePayload: $exception->remotePayload(),
            );

            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed(
                run: $run,
                message: 'No fue posible completar la construcción del candidato.',
            );

            throw $exception;
        }
    }

    /**
     * Activa un candidato previamente construido.
     *
     * @return array{
     *     remote: array<string, mixed>,
     *     training_run: MlTrainingRun
     * }
     */
    public function activate(
        MlTrainingRun $run,
    ): array {
        if ($run->artifact_id === null) {
            throw new RuntimeException(
                'La ejecución no contiene un artefacto activable.',
            );
        }

        if (! in_array(
            $run->status,
            [
                MlTrainingRun::STATUS_BUILT,
                MlTrainingRun::STATUS_ROLLED_BACK,
            ],
            true,
        )) {
            throw new RuntimeException(
                'La ejecución no se encuentra en un estado activable.',
            );
        }

        $remote = $this->client->activateModel(
            $run->artifact_id,
        );

        $updatedRun = DB::transaction(
            static function () use (
                $run,
                $remote,
            ): MlTrainingRun {
                $activatedAt = now();

                /** @var Collection<int, MlTrainingRun> $previouslyActive */
                $previouslyActive = MlTrainingRun::query()
                    ->where('is_active', true)
                    ->whereKeyNot($run->getKey())
                    ->lockForUpdate()
                    ->get();

                foreach ($previouslyActive as $previousRun) {
                    $previousRun->forceFill([
                        'status' => MlTrainingRun::STATUS_ROLLED_BACK,

                        'is_active' => false,

                        'rolled_back_at' => $activatedAt,
                    ])->save();
                }

                $responseHistory = is_array(
                    $run->remote_response,
                )
                    ? $run->remote_response
                    : [];

                $responseHistory[
                    'activation'
                ] = $remote;

                $run->forceFill([
                    'status' => MlTrainingRun::STATUS_ACTIVATED,

                    'is_active' => true,

                    'activated_at' => $activatedAt,

                    'rolled_back_at' => null,

                    'remote_response' => $responseHistory,
                ])->save();

                return $run->fresh([
                    'creator.role',
                ]);
            },
        );

        return [
            'remote' => $remote,

            'training_run' => $updatedRun,
        ];
    }

    /**
     * Solicita a FastAPI la restauración del modelo anterior
     * y sincroniza el estado local de Laravel.
     *
     * @return array{
     *     remote: array<string, mixed>,
     *     training_run: MlTrainingRun|null
     * }
     */
    public function rollback(): array
    {
        $remote = $this->client
            ->rollbackModel();

        $activeArtifactId = data_get(
            $remote,
            'active.artifact_id',
        );

        $restoredRun = DB::transaction(
            static function () use (
                $remote,
                $activeArtifactId,
            ): ?MlTrainingRun {
                $changedAt = now();

                /** @var Collection<int, MlTrainingRun> $activeRuns */
                $activeRuns = MlTrainingRun::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get();

                foreach ($activeRuns as $activeRun) {
                    $history = is_array(
                        $activeRun->remote_response,
                    )
                        ? $activeRun->remote_response
                        : [];

                    $history[
                        'rollback'
                    ] = $remote;

                    $activeRun->forceFill([
                        'status' => MlTrainingRun::STATUS_ROLLED_BACK,

                        'is_active' => false,

                        'rolled_back_at' => $changedAt,

                        'remote_response' => $history,
                    ])->save();
                }

                if (
                    ! is_string($activeArtifactId)
                    || $activeArtifactId === ''
                    || $activeArtifactId
                        === 'legacy-calendar-model'
                ) {
                    return null;
                }

                $restoredRun = MlTrainingRun::query()
                    ->where(
                        'artifact_id',
                        $activeArtifactId,
                    )
                    ->lockForUpdate()
                    ->first();

                if ($restoredRun === null) {
                    return null;
                }

                $history = is_array(
                    $restoredRun->remote_response,
                )
                    ? $restoredRun->remote_response
                    : [];

                $history[
                    'restoration'
                ] = $remote;

                $restoredRun->forceFill([
                    'status' => MlTrainingRun::STATUS_ACTIVATED,

                    'is_active' => true,

                    'activated_at' => $changedAt,

                    'rolled_back_at' => null,

                    'remote_response' => $history,
                ])->save();

                return $restoredRun->fresh([
                    'creator.role',
                ]);
            },
        );

        return [
            'remote' => $remote,

            'training_run' => $restoredRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildDataset(
        array $options,
    ): array {
        $normalized = $this->normalizeOptions(
            $options,
        );

        return $this->datasetService->build(
            dateFrom: $normalized[
                    'date_from'
                ],

            dateTo: $normalized[
                    'date_to'
                ],

            limit: $normalized[
                    'limit'
                ],

            includeEmptyDays: $normalized[
                    'include_empty_days'
                ],
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     date_from: string|null,
     *     date_to: string|null,
     *     limit: int,
     *     include_empty_days: bool
     * }
     */
    private function normalizeOptions(
        array $options,
    ): array {
        return [
            'date_from' => isset($options['date_from'])
                && is_string(
                    $options['date_from'],
                )
                    ? $options['date_from']
                    : null,

            'date_to' => isset($options['date_to'])
                && is_string(
                    $options['date_to'],
                )
                    ? $options['date_to']
                    : null,

            'limit' => max(
                1,
                min(
                    1000,
                    (int) (
                        $options['limit']
                        ?? 365
                    ),
                ),
            ),

            'include_empty_days' => filter_var(
                $options[
                    'include_empty_days'
                ] ?? true,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ) ?? true,
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function datasetHash(
        array $dataset,
    ): string {
        try {
            $json = json_encode(
                $dataset,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new MachineLearningServiceException(
                message: 'No fue posible serializar el dataset de entrenamiento.',

                previous: $exception,
            );
        }

        return hash(
            'sha256',
            $json,
        );
    }

    /**
     * @param  array<string, mixed>|null  $remotePayload
     */
    private function markFailed(
        MlTrainingRun $run,
        string $message,
        ?int $remoteStatus = null,
        ?array $remotePayload = null,
    ): void {
        $run->forceFill([
            'status' => MlTrainingRun::STATUS_FAILED,

            'error_message' => $message,

            'remote_status' => $remoteStatus,

            'remote_response' => [
                'error' => $remotePayload,
            ],

            'failed_at' => now(),
        ])->save();
    }
}
