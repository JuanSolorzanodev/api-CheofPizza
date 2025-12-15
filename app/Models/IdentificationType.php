<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentificationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'identification_name',
        'code',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'identification_type_id');
    }
}
