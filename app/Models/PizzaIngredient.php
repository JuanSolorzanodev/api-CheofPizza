<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PizzaIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'pizza_id',
        'ingredient_id',
    ];

    protected $casts = [
        'pizza_id' => 'integer',
        'ingredient_id' => 'integer',
    ];

    public function pizza(): BelongsTo
    {
        return $this->belongsTo(Pizza::class, 'pizza_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }
}
