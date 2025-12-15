<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PizzaSalesHistory extends Model
{
    use HasFactory;

    protected $table = 'pizza_sales_history';

    protected $fillable = [
        'date',
        'pizza_id',
        'size_id',
        'quantity',
    ];

    protected $casts = [
        'date' => 'date',
        'pizza_id' => 'integer',
        'size_id' => 'integer',
        'quantity' => 'integer',
    ];

    public function pizza(): BelongsTo
    {
        return $this->belongsTo(Pizza::class, 'pizza_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
