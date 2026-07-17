<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'ordered_at',
        'total',
        'delivery_type_id',
        'address',
        'delivery_lat',
        'delivery_lng',
        'delivery_maps_url',
        'delivery_place_id',
        'delivery_reference',
        'payment_method_id',
        'order_status_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'ordered_at' => 'datetime',
        'total' => 'decimal:2',
        'delivery_type_id' => 'integer',
        'delivery_lat' => 'decimal:7',
        'delivery_lng' => 'decimal:7',
        'payment_method_id' => 'integer',
        'order_status_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deliveryType(): BelongsTo
    {
        return $this->belongsTo(
            DeliveryType::class,
            'delivery_type_id'
        );
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(
            PaymentMethod::class,
            'payment_method_id'
        );
    }

    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(
            OrderStatus::class,
            'order_status_id'
        );
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            OrderItem::class,
            'order_id'
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class,
            'order_id'
        );
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(
            OrderStatusChange::class,
            'order_id'
        )->orderBy('changed_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class,
            'order_id'
        );
    }
}
