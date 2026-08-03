<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(
            Role::class,
            'role_id',
        );
    }

    public function carts(): HasMany
    {
        return $this->hasMany(
            Cart::class,
            'user_id',
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(
            Order::class,
            'user_id',
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class,
            'user_id',
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class,
            'user_id',
        );
    }

    public function mlModelRuns(): HasMany
    {
        return $this->hasMany(
            MlModelRun::class,
            'created_by',
        );
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            "{$this->first_name} {$this->last_name}",
        );
    }

    public function isAdmin(): bool
    {
        return strtolower(
            (string) $this->role?->role_name,
        ) === 'admin';
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(
            PaymentReceipt::class,
            'user_id',
        );
    }

    public function reviewedPaymentReceipts(): HasMany
    {
        return $this->hasMany(
            PaymentReceipt::class,
            'reviewed_by',
        );
    }

    public function openedCashSessions(): HasMany
    {
        return $this->hasMany(
            CashSession::class,
            'opened_by',
        );
    }

    public function closedCashSessions(): HasMany
    {
        return $this->hasMany(
            CashSession::class,
            'closed_by',
        );
    }
}
