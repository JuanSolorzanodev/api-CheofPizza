<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cart_status_id',
        'session_id',
        'total',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'cart_status_id' => 'integer',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cartStatus(): BelongsTo
    {
        return $this->belongsTo(CartStatus::class, 'cart_status_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }
}
