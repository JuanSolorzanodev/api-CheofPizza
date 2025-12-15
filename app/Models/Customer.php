<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'identification_type_id',
        'identification',
        'business_name',
        'email',
    ];

    protected $casts = [
        'identification_type_id' => 'integer',
    ];

    public function identificationType(): BelongsTo
    {
        return $this->belongsTo(IdentificationType::class, 'identification_type_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }
}
