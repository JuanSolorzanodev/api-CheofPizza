<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\WhatsAppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BusinessSettingResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly WhatsAppSetting $whatsapp,
    ) {
        parent::__construct(
            $resource,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        return [
            'business' => [
                'name' => (string) $this->business_name,

                'phone' => $this->phone,

                'email' => $this->email,

                'address' => $this->address,
            ],

            'store' => [
                'accepts_orders' => (bool) $this->accepts_orders,

                'closed_message' => $this->closed_message,

                'estimated_minutes' => (int) $this->estimated_minutes,

                'currency' => (string) $this->currency,

                'timezone' => (string) $this->timezone,
            ],

            'delivery' => [
                'pickup_enabled' => (bool) $this->pickup_enabled,

                'delivery_enabled' => (bool) $this->delivery_enabled,

                'delivery_fee' => (float) $this->delivery_fee,

                'minimum_order' => (float) $this->minimum_order,
            ],

            'payments' => [
                'paypal_enabled' => (bool) $this->paypal_enabled,

                'transfer_enabled' => (bool) $this->transfer_enabled,

                'cash_enabled' => (bool) $this->cash_enabled,

                'paypal_configured' => filled(
                    config(
                        'paypal.client_id',
                    ),
                ),
            ],

            'whatsapp' => [
                'active' => (bool) $this->whatsapp->active,

                'phone' => $this->whatsapp->phone,

                'receipt_template' => $this->whatsapp
                    ->receipt_template,
            ],

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
