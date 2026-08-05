<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\MachineLearning;

use App\Models\MlDailyPrediction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MlDailyPrediction
 */
final class MlDailyPredictionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'date' => $this->prediction_date
                ?->toDateString(),

            'day_of_week' => $this->day_of_week,

            'total_units' => $this->total_pizzas,

            'sizes' => [
                'mini' => $this->mini_pizzas,

                'small' => $this->small_pizzas,

                'medium' => $this->medium_pizzas,

                'family' => $this->family_pizzas,

                'giant' => $this->giant_pizzas,
            ],

            'commercial_breakdown' => [
                /*
                 * Estos datos todavía no están disponibles
                 * en el modelo histórico.
                 */
                'basic' => $this->basic,

                'special' => $this->special,

                'estimated_promotions' => $this->estimated_promotions,

                'estimated_regular' => $this->estimated_regular,

                'available' => false,
            ],

            'interval' => [
                'lower_bound' => $this->lower_bound,

                'upper_bound' => $this->upper_bound,

                'confidence_score' => $this->confidence_score,
            ],

            'metadata' => $this->metadata,
        ];
    }
}
