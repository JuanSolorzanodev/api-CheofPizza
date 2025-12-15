<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    use HasFactory;

    protected $fillable = [
        'size_name',
        'portion',
    ];

    protected $casts = [
        'portion' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_size_prices')
            ->withPivot(['price'])
            ->withTimestamps();
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_size_prices')
            ->withPivot(['extra_price'])
            ->withTimestamps();
    }

    public function categorySizePrices(): HasMany
    {
        return $this->hasMany(CategorySizePrice::class, 'size_id');
    }

    public function ingredientSizePrices(): HasMany
    {
        return $this->hasMany(IngredientSizePrice::class, 'size_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'size_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'size_id');
    }

    public function saleBySizes(): HasMany
    {
        return $this->hasMany(SaleBySize::class, 'size_id');
    }

    public function pizzaSalesHistories(): HasMany
    {
        return $this->hasMany(PizzaSalesHistory::class, 'size_id');
    }
}
