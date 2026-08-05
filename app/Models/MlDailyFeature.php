<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class MlDailyFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',

        'total_pizzas_sold',

        'mini_sales',
        'small_sales',
        'medium_sales',
        'family_sales',
        'giant_sales',

        'basic_sales',
        'special_sales',

        'promotion_sales',
        'regular_sales',

        'delivered_orders',
        'cancelled_orders',

        'net_sales',

        'pickup_orders',
        'delivery_orders',

        'consolidated_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',

            'total_pizzas_sold' => 'integer',

            'mini_sales' => 'integer',

            'small_sales' => 'integer',

            'medium_sales' => 'integer',

            'family_sales' => 'integer',

            'giant_sales' => 'integer',

            'basic_sales' => 'integer',

            'special_sales' => 'integer',

            'promotion_sales' => 'integer',

            'regular_sales' => 'integer',

            'delivered_orders' => 'integer',

            'cancelled_orders' => 'integer',

            'net_sales' => 'decimal:2',

            'pickup_orders' => 'integer',

            'delivery_orders' => 'integer',

            'consolidated_at' => 'immutable_datetime',
        ];
    }
}
