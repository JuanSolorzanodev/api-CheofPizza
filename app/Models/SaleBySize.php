<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleBySize extends Model
{
    use HasFactory;

    protected $table = 'sales_by_size';

    protected $fillable = [
        'date',
        'size_id',
        'quantity',
    ];

    protected $casts = [
        'date' => 'date',
        'size_id' => 'integer',
        'quantity' => 'integer',
    ];

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
