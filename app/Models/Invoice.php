<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'voucher_type',
        'establishment_code',
        'emission_point_code',
        'sequential',
        'issued_on',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'customer_id' => 'integer',
        'establishment_code' => 'integer',
        'emission_point_code' => 'integer',
        'issued_on' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
