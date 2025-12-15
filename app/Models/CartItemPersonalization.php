<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemPersonalization extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_item_id',
        'ingredient_id',
        'personalization_action_id',
        'extra_price',
    ];

    protected $casts = [
        'cart_item_id' => 'integer',
        'ingredient_id' => 'integer',
        'personalization_action_id' => 'integer',
        'extra_price' => 'decimal:2',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class, 'cart_item_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function personalizationAction(): BelongsTo
    {
        return $this->belongsTo(PersonalizationAction::class, 'personalization_action_id');
    }
}
