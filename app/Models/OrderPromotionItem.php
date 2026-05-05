<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPromotionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'pizza_id',
        'pizza_name',
    ];

    protected $casts = [
        'order_item_id' => 'integer',
        'pizza_id' => 'integer',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function pizza(): BelongsTo
    {
        return $this->belongsTo(Pizza::class, 'pizza_id');
    }

    public function personalizations(): HasMany
    {
        return $this->hasMany(OrderItemPersonalization::class, 'order_promotion_item_id');
    }
}
