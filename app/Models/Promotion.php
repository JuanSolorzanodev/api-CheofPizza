<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'promotion_name',
        'description',
        'promotion_price',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'promotion_price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function promotionDetails(): HasMany
    {
        return $this->hasMany(PromotionDetail::class, 'promotion_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'promotion_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'promotion_id');
    }
}
