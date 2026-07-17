<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderStatus extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'status_name' => 'string',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(
            Order::class,
            'order_status_id',
        );
    }

    public function changesFrom(): HasMany
    {
        return $this->hasMany(
            OrderStatusChange::class,
            'from_order_status_id',
        );
    }

    public function changesTo(): HasMany
    {
        return $this->hasMany(
            OrderStatusChange::class,
            'to_order_status_id',
        );
    }
}
