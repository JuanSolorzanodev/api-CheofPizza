<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySizePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'size_id',
        'price',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'size_id' => 'integer',
        'price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
