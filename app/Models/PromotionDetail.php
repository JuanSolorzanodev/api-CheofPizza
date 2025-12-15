<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_id',
        'category_id',
        'size_id',
        'required_quantity',
    ];

    protected $casts = [
        'promotion_id' => 'integer',
        'category_id' => 'integer',
        'size_id' => 'integer',
        'required_quantity' => 'integer',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
