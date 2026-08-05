<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BusinessSetting extends Model
{
    protected $fillable = [
        'business_name',
        'phone',
        'email',
        'address',

        'accepts_orders',
        'closed_message',
        'estimated_minutes',
        'currency',
        'timezone',

        'pickup_enabled',
        'delivery_enabled',
        'delivery_fee',
        'minimum_order',

        'paypal_enabled',
        'transfer_enabled',
        'cash_enabled',
    ];

    protected $casts = [
        'accepts_orders' => 'boolean',
        'estimated_minutes' => 'integer',

        'pickup_enabled' => 'boolean',
        'delivery_enabled' => 'boolean',
        'delivery_fee' => 'decimal:2',
        'minimum_order' => 'decimal:2',

        'paypal_enabled' => 'boolean',
        'transfer_enabled' => 'boolean',
        'cash_enabled' => 'boolean',
    ];

    /**
     * Configuración inicial utilizada cuando todavía
     * no existe el registro singleton.
     *
     * @return array<string, mixed>
     */
    public static function defaultValues(): array
    {
        return [
            'business_name' => "CHEO' PIZZA",
            'phone' => null,
            'email' => null,
            'address' => null,

            'accepts_orders' => true,
            'closed_message' => 'En este momento la tienda no está recibiendo pedidos.',
            'estimated_minutes' => 35,
            'currency' => 'USD',
            'timezone' => 'America/Guayaquil',

            'pickup_enabled' => true,
            'delivery_enabled' => true,
            'delivery_fee' => '0.00',
            'minimum_order' => '0.00',

            'paypal_enabled' => true,
            'transfer_enabled' => true,
            'cash_enabled' => true,
        ];
    }
}
