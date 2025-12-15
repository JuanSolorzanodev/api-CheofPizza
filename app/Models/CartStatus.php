<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'status_name',
    ];

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'cart_status_id');
    }
}
