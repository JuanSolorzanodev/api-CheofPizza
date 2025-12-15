<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_type_id',
        'ingredient_name',
    ];

    protected $casts = [
        'ingredient_type_id' => 'integer',
    ];

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(Size::class, 'ingredient_size_prices')
            ->withPivot(['extra_price'])
            ->withTimestamps();
    }

    public function pizzas(): BelongsToMany
    {
        return $this->belongsToMany(Pizza::class, 'pizza_ingredients')
            ->withTimestamps();
    }

    public function ingredientType(): BelongsTo
    {
        return $this->belongsTo(IngredientType::class, 'ingredient_type_id');
    }

    public function pizzaIngredients(): HasMany
    {
        return $this->hasMany(PizzaIngredient::class, 'ingredient_id');
    }

    public function ingredientSizePrices(): HasMany
    {
        return $this->hasMany(IngredientSizePrice::class, 'ingredient_id');
    }

    public function cartItemPersonalizations(): HasMany
    {
        return $this->hasMany(CartItemPersonalization::class, 'ingredient_id');
    }

    public function orderItemPersonalizations(): HasMany
    {
        return $this->hasMany(OrderItemPersonalization::class, 'ingredient_id');
    }
}
