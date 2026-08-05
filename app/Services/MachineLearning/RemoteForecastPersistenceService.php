<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Exceptions\MachineLearningServiceException;
use App\Models\MlModelRun;
use App\Models\User;
use Illuminate\Support\Arr;

final class RemoteForecastPersistenceService
{
    public function __construct(
        private readonly ForecastImportService $importService,
    ) {}

    /**
     * Convierte la respuesta de FastAPI al formato interno
     * utilizado por Laravel y la guarda de manera idempotente.
     *
     * @param  array<string, mixed>  $forecast
     */
    public function persist(
        array $forecast,
        User $admin,
    ): MlModelRun {
        $this->validateRemotePayload(
            $forecast
        );

        $normalized = $this->normalize(
            $forecast
        );

        /*
         * El hash no incluye generated_at porque FastAPI genera
         * un timestamp diferente en cada petición.
         *
         * De esta manera, pedir dos veces el mismo pronóstico
         * no crea dos ejecuciones duplicadas.
         */
        $sourceHash = hash(
            'sha256',
            json_encode(
                [
                    'model' => [
                        'type' => Arr::get(
                            $forecast,
                            'model.type'
                        ),

                        'version' => Arr::get(
                            $forecast,
                            'model.version'
                        ),
                    ],

                    'forecast_from' => $forecast['forecast_from'],

                    'forecast_until' => $forecast['forecast_until'],

                    'predictions' => $forecast['predictions'],
                ],
                JSON_THROW_ON_ERROR
            )
        );

        return $this->importService->import(
            payload: $normalized,
            admin: $admin,
            source: MlModelRun::SOURCE_ML_SERVICE,
            version: (string) Arr::get(
                $forecast,
                'model.version'
            ),
            metadata: [
                'generation_mode' => 'remote_inference',

                'service' => 'cheofpizza-ml',

                'model_type' => Arr::get(
                    $forecast,
                    'model.type'
                ),

                'features' => Arr::get(
                    $forecast,
                    'model.features',
                    []
                ),

                'limitations' => Arr::get(
                    $forecast,
                    'limitations',
                    []
                ),
            ],
            sourceHash: $sourceHash,
        );
    }

    /**
     * @param  array<string, mixed>  $forecast
     * @return array<string, mixed>
     */
    private function normalize(
        array $forecast
    ): array {
        /** @var array<int, array<string, mixed>> $metrics */
        $metrics = Arr::get(
            $forecast,
            'model.metrics',
            []
        );

        $models = collect($metrics)
            ->mapWithKeys(
                static function (
                    array $metric
                ): array {
                    $target = (string) (
                        $metric['target']
                        ?? ''
                    );

                    return [
                        $target => [
                            'name' => (string) (
                                $metric['algorithm']
                                ?? ''
                            ),

                            'selection_score' => $metric[
                                    'selection_score'
                                ] ?? null,

                            'test_mae' => $metric['mae']
                                ?? null,

                            'test_rmse' => $metric['rmse']
                                ?? null,

                            'test_smape' => $metric['smape']
                                ?? null,

                            'test_r2' => $metric['r2']
                                ?? null,

                            'cv_mae' => $metric['cv_mae']
                                ?? null,

                            'cv_rmse' => $metric['cv_rmse']
                                ?? null,
                        ],
                    ];
                }
            )
            ->all();

        /** @var array<int, array<string, mixed>> $remotePredictions */
        $remotePredictions = $forecast[
            'predictions'
        ];

        $predictions = collect(
            $remotePredictions
        )
            ->map(
                static function (
                    array $prediction
                ): array {
                    /** @var array<string, mixed> $sizes */
                    $sizes = $prediction[
                        'sizes'
                    ];

                    return [
                        'date' => (string) $prediction[
                                'date'
                            ],

                        'day_of_week' => (string) (
                            $prediction[
                                'day_of_week'
                            ] ?? ''
                        ),

                        'total_units' => (int) $prediction[
                                'total_units'
                            ],

                        'mini' => (int) (
                            $sizes['mini']
                            ?? 0
                        ),

                        'small' => (int) (
                            $sizes['small']
                            ?? 0
                        ),

                        'medium' => (int) (
                            $sizes['medium']
                            ?? 0
                        ),

                        'family' => (int) (
                            $sizes['family']
                            ?? 0
                        ),
                    ];
                }
            )
            ->all();

        /** @var array<string, mixed> $summary */
        $summary = $forecast['summary'];

        return [
            'generated_at' => $forecast['generated_at'],

            'trained_from' => Arr::get(
                $forecast,
                'model.trained_from'
            ),

            'trained_until' => Arr::get(
                $forecast,
                'model.trained_until'
            ),

            'historical_days' => (int) Arr::get(
                $forecast,
                'model.training_records'
            ),

            'forecast_days' => (int) $forecast[
                    'forecast_days'
                ],

            'models' => $models,

            'summary' => array_merge(
                $summary,
                [
                    /*
                     * El microservicio actual no devuelve
                     * este acumulado histórico.
                     */
                    'historical_total_units' => null,
                ]
            ),

            'recommendations' => $this->recommendations(
                $summary
            ),

            'predictions' => $predictions,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, string>
     */
    private function recommendations(
        array $summary
    ): array {
        $highestDate = (string) (
            $summary[
                'highest_demand_date'
            ] ?? ''
        );

        $highestDay = (string) (
            $summary[
                'highest_demand_day'
            ] ?? ''
        );

        $highestUnits = (int) (
            $summary[
                'highest_demand_units'
            ] ?? 0
        );

        $highestSize = (string) (
            $summary[
                'highest_demand_size'
            ] ?? ''
        );

        $dailyAverage = (float) (
            $summary[
                'forecast_daily_average'
            ] ?? 0
        );

        return [
            sprintf(
                'El día con mayor demanda estimada es %s %s, con aproximadamente %d pizzas.',
                $highestDay,
                $highestDate,
                $highestUnits,
            ),

            sprintf(
                'El tamaño con mayor demanda proyectada es %s.',
                $this->translateSize(
                    $highestSize
                ),
            ),

            sprintf(
                'El promedio estimado del periodo es de %.2f pizzas diarias.',
                $dailyAverage,
            ),

            'Conviene preparar ingredientes, masa y personal antes de los días que superen el promedio pronosticado.',
        ];
    }

    private function translateSize(
        string $size
    ): string {
        return match ($size) {
            'mini' => 'Mini',
            'small' => 'Pequeña',
            'medium' => 'Mediana',
            'family' => 'Familiar',
            default => $size,
        };
    }

    /**
     * @param  array<string, mixed>  $forecast
     */
    private function validateRemotePayload(
        array $forecast
    ): void {
        $requiredPaths = [
            'generated_at',
            'forecast_from',
            'forecast_until',
            'forecast_days',
            'model.type',
            'model.version',
            'model.trained_from',
            'model.trained_until',
            'model.training_records',
            'model.metrics',
            'summary',
            'predictions',
        ];

        foreach ($requiredPaths as $path) {
            if (! Arr::has($forecast, $path)) {
                throw new MachineLearningServiceException(
                    sprintf(
                        'La respuesta predictiva no contiene el campo requerido: %s.',
                        $path,
                    )
                );
            }
        }

        $predictions = Arr::get(
            $forecast,
            'predictions'
        );

        if (
            ! is_array($predictions)
            || $predictions === []
        ) {
            throw new MachineLearningServiceException(
                'La respuesta predictiva no contiene predicciones válidas.'
            );
        }

        $metrics = Arr::get(
            $forecast,
            'model.metrics'
        );

        if (
            ! is_array($metrics)
            || $metrics === []
        ) {
            throw new MachineLearningServiceException(
                'La respuesta predictiva no contiene métricas válidas.'
            );
        }

        $hasTotalModel = collect($metrics)
            ->contains(
                static fn (
                    mixed $metric
                ): bool => is_array($metric)
                    && (
                        $metric['target']
                        ?? null
                    ) === 'total_units'
            );

        if (! $hasTotalModel) {
            throw new MachineLearningServiceException(
                'La respuesta predictiva no contiene el modelo total_units.'
            );
        }
    }
}
