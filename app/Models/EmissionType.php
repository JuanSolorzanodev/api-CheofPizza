<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmissionType extends Model
{
    use HasFactory;

    protected $fillable = [
        'emission_name',
        'code',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'emission_type_id');
    }
}
