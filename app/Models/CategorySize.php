<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CategorySize extends Pivot
{
    protected $table = 'category_size';

    protected $fillable = [
        'category_id',
        'size_id',
        'price'
    ];

    protected $casts = [

        'price' => 'decimal:2'

    ];
}
