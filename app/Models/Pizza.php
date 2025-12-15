<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pizza extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'pizza_name',
        'description',
        'image_url',
        'is_visible',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'is_visible' => 'boolean',
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'pizza_ingredients')
            ->withTimestamps();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function pizzaIngredients(): HasMany
    {
        return $this->hasMany(PizzaIngredient::class, 'pizza_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'pizza_id');
    }

    public function cartPromotionItems(): HasMany
    {
        return $this->hasMany(CartPromotionItem::class, 'pizza_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'pizza_id');
    }

    public function orderPromotionItems(): HasMany
    {
        return $this->hasMany(OrderPromotionItem::class, 'pizza_id');
    }

    public function pizzaSalesHistories(): HasMany
    {
        return $this->hasMany(PizzaSalesHistory::class, 'pizza_id');
    }
}
