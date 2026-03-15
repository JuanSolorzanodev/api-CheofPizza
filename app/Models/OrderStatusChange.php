<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusChange extends Model
{
    protected $fillable = [
        'order_id',
        'from_order_status_id',
        'to_order_status_id',
        'changed_by_user_id',
        'changed_at',
        'note',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'from_order_status_id' => 'integer',
        'to_order_status_id' => 'integer',
        'changed_by_user_id' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'from_order_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'to_order_status_id');
    }

    public function changedBy(): BelongsTo
    {
    return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
