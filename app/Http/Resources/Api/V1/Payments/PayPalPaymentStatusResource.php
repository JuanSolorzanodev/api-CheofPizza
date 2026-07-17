<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Payments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 */
final class PayPalPaymentStatusResource extends JsonResource
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

            'paypal_capture_id' =>
                $this->provider_capture_id,

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

            'is_terminal' =>
                ! $this->isPending(),

            'can_retry_capture' =>
                $this->canBeCaptured(),

            'order' =>
                $this->when(
                    $this->relationLoaded('order')
                    && $this->order !== null,

                    fn (): array => [
                        'id' =>
                            $this->order->id,

                        'order_number' =>
                            $this->order->order_number,

                        'status' =>
                            $this->order->orderStatus
                                ?->status_name,

                        'total' =>
                            number_format(
                                (float) $this->order->total,
                                2,
                                '.',
                                '',
                            ),

                        'ordered_at' =>
                            $this->order->ordered_at
                                ?->toISOString(),
                    ],
                ),

            'approved_at' =>
                $this->approved_at
                    ?->toISOString(),

            'paid_at' =>
                $this->paid_at
                    ?->toISOString(),

            'failed_at' =>
                $this->failed_at
                    ?->toISOString(),

            'cancelled_at' =>
                $this->cancelled_at
                    ?->toISOString(),

            'refunded_at' =>
                $this->refunded_at
                    ?->toISOString(),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}
