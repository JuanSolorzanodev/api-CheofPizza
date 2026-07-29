<?php

declare(strict_types=1);

namespace App\Services\MachineLearning;

use App\Models\MlDailyPrediction;
use App\Models\MlModelRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ForecastImportService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function import(
        array $payload,
        User $admin
    ): MlModelRun {
        $normalizedPayload = $this->normalizePayload(
            $payload
        );

        $sourceHash = hash(
            'sha256',
            json_encode(
                $normalizedPayload,
                JSON_THROW_ON_ERROR
            )
        );

        $existingRun = MlModelRun::query()
            ->with([
                'predictions' => static fn ($query) =>
                    $query->orderBy('prediction_date'),
            ])
            ->where('source_hash', $sourceHash)
            ->first();

        if ($existingRun !== null) {
            return $existingRun;
        }

        return DB::transaction(
            function () use (
                $normalizedPayload,
                $sourceHash,
                $admin
            ): MlModelRun {
                /** @var array<string, mixed> $totalModel */
                $totalModel = $normalizedPayload[
                    'models'
                ]['total_units'];

                /** @var array<int, array<string, mixed>> $predictions */
                $predictions = $normalizedPayload[
                    'predictions'
                ];

                $generatedAt = CarbonImmutable::parse(
                    $normalizedPayload['generated_at']
                );

                $trainedUntil = CarbonImmutable::parse(
                    $normalizedPayload['trained_until']
                );

                $forecastDates = collect($predictions)
                    ->pluck('date')
                    ->map(
                        static fn (mixed $date): CarbonImmutable =>
                            CarbonImmutable::parse(
                                (string) $date
                            )
                    );

                $version = sprintf(
                    'v%s-%s',
                    $trainedUntil->format('Ymd'),
                    Str::lower(
                        Str::random(8)
                    )
                );

                $run = MlModelRun::query()->create([
                    'uuid' => (string) Str::uuid(),

                    'source_hash' => $sourceHash,

                    'source' =>
                        MlModelRun::SOURCE_GOOGLE_COLAB,

                    'status' =>
                        MlModelRun::STATUS_COMPLETED,

                    'algorithm' =>
                        (string) $totalModel['name'],

                    'target' =>
                        MlModelRun::TARGET_TOTAL_UNITS,

                    'version' => $version,

                    'trained_from' =>
                        $normalizedPayload['trained_from'],

                    'trained_until' =>
                        $normalizedPayload['trained_until'],

                    'training_records' =>
                        (int) $normalizedPayload[
                            'historical_days'
                        ],

                    'forecast_days' =>
                        (int) $normalizedPayload[
                            'forecast_days'
                        ],

                    'forecast_from' =>
                        $forecastDates
                            ->min()
                            ->toDateString(),

                    'forecast_until' =>
                        $forecastDates
                            ->max()
                            ->toDateString(),

                    'selection_score' =>
                        Arr::get(
                            $totalModel,
                            'selection_score'
                        ),

                    'mae' =>
                        Arr::get(
                            $totalModel,
                            'test_mae'
                        ),

                    'rmse' =>
                        Arr::get(
                            $totalModel,
                            'test_rmse'
                        ),

                    'smape' =>
                        Arr::get(
                            $totalModel,
                            'test_smape'
                        ),

                    'r2' =>
                        Arr::get(
                            $totalModel,
                            'test_r2'
                        ),

                    'cv_mae' =>
                        Arr::get(
                            $totalModel,
                            'cv_mae'
                        ),

                    'cv_rmse' =>
                        Arr::get(
                            $totalModel,
                            'cv_rmse'
                        ),

                    'generated_at' => $generatedAt,

                    'is_active' => false,

                    'models' =>
                        $normalizedPayload['models'],

                    'summary' =>
                        $normalizedPayload['summary'],

                    'recommendations' =>
                        $normalizedPayload[
                            'recommendations'
                        ],

                    'metadata' => [
                        'imported_at' =>
                            now()->toIso8601String(),

                        'source_file' =>
                            'forecast_next_7_days.json',
                    ],

                    'created_by' => $admin->id,
                ]);

                $this->insertPredictions(
                    run: $run,
                    predictions: $predictions
                );

                $this->activateWhenBetter(
                    $run
                );

                return $run->load([
                    'predictions' => static fn ($query) =>
                        $query->orderBy('prediction_date'),
                    'creator.role',
                ]);
            }
        );
    }

    /**
     * @param array<int, array<string, mixed>> $predictions
     */
    private function insertPredictions(
        MlModelRun $run,
        array $predictions
    ): void {
        $timestamp = now();

        $rows = collect($predictions)
            ->map(
                static function (
                    array $prediction
                ) use (
                    $run,
                    $timestamp
                ): array {
                    return [
                        'ml_model_run_id' => $run->id,

                        'prediction_date' =>
                            $prediction['date'],

                        'day_of_week' =>
                            $prediction['day_of_week']
                            ?? null,

                        'total_pizzas' =>
                            (int) $prediction[
                                'total_units'
                            ],

                        'mini_pizzas' =>
                            (int) $prediction['mini'],

                        'small_pizzas' =>
                            (int) $prediction['small'],

                        'medium_pizzas' =>
                            (int) $prediction['medium'],

                        'family_pizzas' =>
                            (int) $prediction['family'],

                        /*
                         * El modelo actual no predice estas
                         * variables todavía.
                         */
                        'giant_pizzas' => 0,
                        'basic' => 0,
                        'special' => 0,
                        'estimated_promotions' => 0,
                        'estimated_regular' => 0,

                        'lower_bound' => null,
                        'upper_bound' => null,
                        'confidence_score' => null,

                        'metadata' => json_encode([
                            'data_scope' =>
                                'historical_size_only',

                            'flavor_prediction_available' =>
                                false,
                        ], JSON_THROW_ON_ERROR),

                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            )
            ->all();

        MlDailyPrediction::query()->insert(
            $rows
        );
    }

    private function activateWhenBetter(
        MlModelRun $newRun
    ): void {
        $activeRun = MlModelRun::query()
            ->where('target', $newRun->target)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($activeRun === null) {
            $this->activate($newRun);

            return;
        }

        if ($newRun->mae === null) {
            return;
        }

        if (
            $activeRun->mae !== null
            && (float) $newRun->mae
                > (float) $activeRun->mae
        ) {
            return;
        }

        $activeRun->forceFill([
            'is_active' => false,
            'activated_at' => null,
        ])->save();

        $this->activate($newRun);
    }

    private function activate(
        MlModelRun $run
    ): void {
        $run->forceFill([
            'is_active' => true,
            'activated_at' => now(),
        ])->save();
    }

    /**
     * Normaliza recursivamente las claves para que el hash
     * sea estable aunque cambie el orden del JSON.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(
        array $payload
    ): array {
        $normalize = function (
            mixed $value
        ) use (
            &$normalize
        ): mixed {
            if (!is_array($value)) {
                return $value;
            }

            if (array_is_list($value)) {
                return array_map(
                    $normalize,
                    $value
                );
            }

            ksort($value);

            foreach ($value as $key => $item) {
                $value[$key] = $normalize(
                    $item
                );
            }

            return $value;
        };

        /** @var array<string, mixed> */
        return $normalize($payload);
    }
}
