<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleByCategory extends Model
{
    use HasFactory;

    protected $table = 'sales_by_category';

    protected $fillable = [
        'date',
        'category_id',
        'quantity',
    ];

    protected $casts = [
        'date' => 'date',
        'category_id' => 'integer',
        'quantity' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
