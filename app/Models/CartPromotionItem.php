<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartPromotionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_item_id',
        'pizza_id',
    ];

    protected $casts = [
        'cart_item_id' => 'integer',
        'pizza_id' => 'integer',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class, 'cart_item_id');
    }

    public function pizza(): BelongsTo
    {
        return $this->belongsTo(Pizza::class, 'pizza_id');
    }

    public function personalizations(): HasMany
    {
        return $this->hasMany(CartItemPersonalization::class, 'cart_promotion_item_id');
    }
}
