<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'item_type',
        'pizza_id',
        'promotion_id',
        'size_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'cart_id' => 'integer',
        'pizza_id' => 'integer',
        'promotion_id' => 'integer',
        'size_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function pizza(): BelongsTo
    {
        return $this->belongsTo(Pizza::class, 'pizza_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function cartItemPersonalizations(): HasMany
    {
        return $this->hasMany(CartItemPersonalization::class, 'cart_item_id');
    }

    public function cartPromotionItems(): HasMany
    {
        return $this->hasMany(CartPromotionItem::class, 'cart_item_id');
    }
}
