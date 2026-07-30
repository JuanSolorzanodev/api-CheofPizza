<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Promotion extends Model
{
    use HasFactory;

    public const TYPE_FIXED_COMBO =
        'fixed_combo';

    public const TYPE_SIZE_FIXED_PRICE =
        'size_fixed_price';

    public $timestamps = false;

    protected $fillable = [
        'promotion_name',
        'slug',
        'description',
        'banner_image_url',
        'promotion_type',
        'selection_quantity',
        'promotion_price',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'promotion_price' => 'decimal:2',
        'selection_quantity' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function promotionDetails(): HasMany
    {
        return $this->hasMany(
            PromotionDetail::class,
            'promotion_id'
        );
    }

    public function sizePrices(): HasMany
    {
        return $this->hasMany(
            PromotionSizePrice::class,
            'promotion_id'
        );
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(
            CartItem::class,
            'promotion_id'
        );
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            OrderItem::class,
            'promotion_id'
        );
    }

    public function isFixedCombo(): bool
    {
        return $this->promotion_type ===
            self::TYPE_FIXED_COMBO;
    }

    public function isSizeFixedPrice(): bool
    {
        return $this->promotion_type ===
            self::TYPE_SIZE_FIXED_PRICE;
    }
}
