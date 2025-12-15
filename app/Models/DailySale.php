<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySale extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'total_orders',
        'total_pizzas',
        'total_promotions',
        'total_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'total_orders' => 'integer',
        'total_pizzas' => 'integer',
        'total_promotions' => 'integer',
        'total_amount' => 'decimal:2',
    ];
}
