<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'ruc',
        'business_name',
        'headquarters_address',
        'establishment_code',
        'emission_point_code',
        'special_taxpayer',
        'accounting_required',
        'logo_path',
        'environment_type_id',
        'emission_type_id',
        'signature_path',
        'signature_password',
    ];

    protected $casts = [
        'accounting_required' => 'boolean',
        'environment_type_id' => 'integer',
        'emission_type_id' => 'integer',
    ];

    public function environmentType(): BelongsTo
    {
        return $this->belongsTo(EnvironmentType::class, 'environment_type_id');
    }

    public function emissionType(): BelongsTo
    {
        return $this->belongsTo(EmissionType::class, 'emission_type_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'company_id');
    }
}
