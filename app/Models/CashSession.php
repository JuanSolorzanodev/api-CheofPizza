<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CashSession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'opened_by',
        'closed_by',
        'status',
        'opening_amount',
        'expected_cash',
        'counted_cash',
        'difference',
        'opened_at',
        'closed_at',
        'opening_note',
        'closing_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'opened_by' => 'integer',
            'closed_by' => 'integer',
            'status' => CashSessionStatus::class,
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'opened_by',
        );
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by',
        );
    }

    public function movements(): HasMany
    {
        return $this->hasMany(
            CashMovement::class,
            'cash_session_id',
        );
    }
}
