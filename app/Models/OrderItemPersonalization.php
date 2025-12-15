<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemPersonalization extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'ingredient_id',
        'ingredient_name',
        'personalization_action_id',
        'modification_type',
        'extra_price',
    ];

    protected $casts = [
        'order_item_id' => 'integer',
        'ingredient_id' => 'integer',
        'personalization_action_id' => 'integer',
        'extra_price' => 'decimal:2',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
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
