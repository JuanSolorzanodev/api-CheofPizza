<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CashMovement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'cash_session_id',
        'created_by',
        'type',
        'amount',
        'reason',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'cash_session_id' => 'integer',
            'created_by' => 'integer',
            'type' => CashMovementType::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(
            CashSession::class,
            'cash_session_id',
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }
}
