<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PayPalWebhookEvent extends Model
{
    protected $table = 'paypal_webhook_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_type',
        'resource_type',
        'provider_order_id',
        'provider_capture_id',
        'verification_status',
        'processing_status',
        'payload',
        'failure_message',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
