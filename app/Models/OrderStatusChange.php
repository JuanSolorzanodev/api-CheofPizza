<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusChange extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'from_order_status_id',
        'to_order_status_id',
        'changed_by_user_id',
        'changed_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'order_id' => 'integer',
            'from_order_status_id' => 'integer',
            'to_order_status_id' => 'integer',
            'changed_by_user_id' => 'integer',
            'changed_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'order_id',
        );
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(
            OrderStatus::class,
            'from_order_status_id',
        );
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(
            OrderStatus::class,
            'to_order_status_id',
        );
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'changed_by_user_id',
        );
    }
}
