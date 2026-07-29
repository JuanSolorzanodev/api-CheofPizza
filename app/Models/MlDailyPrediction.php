<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MlDailyPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'ml_model_run_id',
        'prediction_date',
        'day_of_week',
        'total_pizzas',
        'mini_pizzas',
        'small_pizzas',
        'medium_pizzas',
        'family_pizzas',
        'giant_pizzas',
        'basic',
        'special',
        'estimated_promotions',
        'estimated_regular',
        'lower_bound',
        'upper_bound',
        'confidence_score',
        'metadata',
    ];

    protected $casts = [
        'ml_model_run_id' => 'integer',

        'prediction_date' => 'date',

        'total_pizzas' => 'integer',
        'mini_pizzas' => 'integer',
        'small_pizzas' => 'integer',
        'medium_pizzas' => 'integer',
        'family_pizzas' => 'integer',
        'giant_pizzas' => 'integer',

        'basic' => 'integer',
        'special' => 'integer',
        'estimated_promotions' => 'integer',
        'estimated_regular' => 'integer',

        'lower_bound' => 'decimal:2',
        'upper_bound' => 'decimal:2',
        'confidence_score' => 'decimal:4',

        'metadata' => 'array',
    ];

    public function modelRun(): BelongsTo
    {
        return $this->belongsTo(
            MlModelRun::class,
            'ml_model_run_id'
        );
    }
}
