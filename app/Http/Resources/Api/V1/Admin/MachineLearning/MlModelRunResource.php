<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\MachineLearning;

use App\Models\MlModelRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MlModelRun
 */
final class MlModelRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            'source' => $this->source,
            'status' => $this->status,

            'model' => [
                'algorithm' =>
                    $this->algorithm,

                'target' =>
                    $this->target,

                'version' =>
                    $this->version,

                'is_active' =>
                    $this->is_active,

                'activated_at' =>
                    $this->activated_at
                        ?->toIso8601String(),
            ],

            'training' => [
                'from' =>
                    $this->trained_from
                        ?->toDateString(),

                'until' =>
                    $this->trained_until
                        ?->toDateString(),

                'records' =>
                    $this->training_records,
            ],

            'forecast' => [
                'days' =>
                    $this->forecast_days,

                'from' =>
                    $this->forecast_from
                        ?->toDateString(),

                'until' =>
                    $this->forecast_until
                        ?->toDateString(),

                'generated_at' =>
                    $this->generated_at
                        ?->toIso8601String(),
            ],

            'metrics' => [
                'selection_score' =>
                    $this->selection_score,

                'mae' =>
                    $this->mae,

                'rmse' =>
                    $this->rmse,

                'smape' =>
                    $this->smape,

                'r2' =>
                    $this->r2,

                'cv_mae' =>
                    $this->cv_mae,

                'cv_rmse' =>
                    $this->cv_rmse,
            ],

            'all_models' =>
                $this->models,

            'summary' =>
                $this->summary,

            'recommendations' =>
                $this->recommendations,

            'limitations' => [
                'scope' =>
                    'daily_demand_by_size',

                'flavor_prediction_available' =>
                    false,

                'hourly_prediction_available' =>
                    false,

                'message' =>
                    'Modelo inicial basado en fecha y tamaño. La precisión por sabor y horario mejorará con los pedidos digitales.',
            ],

            'predictions' =>
                MlDailyPredictionResource::collection(
                    $this->whenLoaded(
                        'predictions'
                    )
                ),

            'created_by' =>
                $this->whenLoaded(
                    'creator',
                    fn (): array => [
                        'id' =>
                            $this->creator->id,

                        'name' =>
                            trim(
                                $this->creator->first_name
                                . ' '
                                . $this->creator->last_name
                            ),

                        'email' =>
                            $this->creator->email,
                    ]
                ),

            'created_at' =>
                $this->created_at
                    ?->toIso8601String(),
        ];
    }
}
