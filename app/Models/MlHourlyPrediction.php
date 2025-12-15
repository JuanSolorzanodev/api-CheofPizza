<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MlHourlyPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'prediction_date',
        'hour',
        'estimated_quantity',
    ];

    protected $casts = [
        'prediction_date' => 'date',
        'hour' => 'string',
        'estimated_quantity' => 'integer',
    ];
}
