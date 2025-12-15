<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MlDailyFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'total_pizzas_sold',
        'small_sales',
        'medium_sales',
        'family_sales',
        'giant_sales',
        'basic_sales',
        'special_sales',
        'promotion_sales',
        'regular_sales',
    ];

    protected $casts = [
        'date' => 'date',
        'total_pizzas_sold' => 'integer',
        'small_sales' => 'integer',
        'medium_sales' => 'integer',
        'family_sales' => 'integer',
        'giant_sales' => 'integer',
        'basic_sales' => 'integer',
        'special_sales' => 'integer',
        'promotion_sales' => 'integer',
        'regular_sales' => 'integer',
    ];
}
