<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleByHour extends Model
{
    use HasFactory;

    protected $table = 'sales_by_hour';

    protected $fillable = [
        'date',
        'hour',
        'quantity',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'string',
        'quantity' => 'integer',
    ];
}
