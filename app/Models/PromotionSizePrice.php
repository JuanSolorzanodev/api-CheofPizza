<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PromotionSizePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'size_id',
        'fixed_price',
    ];

    protected $casts = [
        'promotion_id' => 'integer',
        'size_id' => 'integer',
        'fixed_price' => 'decimal:2',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(
            Promotion::class,
            'promotion_id'
        );
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(
            Size::class,
            'size_id'
        );
    }
}
