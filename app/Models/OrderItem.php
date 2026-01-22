<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'promotion_id',
        'promotion_name',
        'pizza_id',
        'pizza_name',
        'pizza_id_second',
        'pizza_name_second',
        'size_id',
        'size_name',
        'category_name',
        'category_name_second',
        'is_half_and_half',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'promotion_id' => 'integer',
        'pizza_id' => 'integer',
        'pizza_id_second' => 'integer',
        'size_id' => 'integer',
        'is_half_and_half' => 'boolean',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function pizza(): BelongsTo
    {
        return $this->belongsTo(Pizza::class, 'pizza_id');
    }

    public function pizzaSecond(): BelongsTo
    {
        return $this->belongsTo(Pizza::class, 'pizza_id_second');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function orderItemPersonalizations(): HasMany
    {
        return $this->hasMany(OrderItemPersonalization::class, 'order_item_id');
    }

    public function orderPromotionItems(): HasMany
    {
        return $this->hasMany(OrderPromotionItem::class, 'order_item_id');
    }
}
