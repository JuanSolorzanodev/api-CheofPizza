<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 */
final class PayPalOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'payment_id' =>
                $this->uuid,

            'paypal_order_id' =>
                $this->provider_order_id,

            'status' =>
                $this->status->value,

            'provider_status' =>
                $this->provider_status,

            'amount' =>
                number_format(
                    (float) $this->amount,
                    2,
                    '.',
                    '',
                ),

            'currency' =>
                $this->currency,

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
