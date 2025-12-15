<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryType extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_type_name',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_type_id');
    }
}
