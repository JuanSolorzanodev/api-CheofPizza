<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MlDailyPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'prediction_date',
        'total_pizzas',
        'small_pizzas',
        'medium_pizzas',
        'family_pizzas',
        'giant_pizzas',
        'basic',
        'special',
        'estimated_promotions',
        'estimated_regular',
    ];

    protected $casts = [
        'prediction_date' => 'date',
        'total_pizzas' => 'integer',
        'small_pizzas' => 'integer',
        'medium_pizzas' => 'integer',
        'family_pizzas' => 'integer',
        'giant_pizzas' => 'integer',
        'basic' => 'integer',
        'special' => 'integer',
        'estimated_promotions' => 'integer',
        'estimated_regular' => 'integer',
    ];
}
