<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalizationAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_name',
        'description'
    ];

    public function cartItemPersonalizations(): HasMany
    {
        return $this->hasMany(CartItemPersonalization::class, 'personalization_action_id');
    }

    public function orderItemPersonalizations(): HasMany
    {
        return $this->hasMany(OrderItemPersonalization::class, 'personalization_action_id');
    }
}
