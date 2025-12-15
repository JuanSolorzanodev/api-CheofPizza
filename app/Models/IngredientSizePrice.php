<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientSizePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'size_id',
        'extra_price',
    ];

    protected $casts = [
        'ingredient_id' => 'integer',
        'size_id' => 'integer',
        'extra_price' => 'decimal:2',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
