<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BankAccount;
use App\Models\WhatsAppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicBusinessSettingResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly WhatsAppSetting $whatsapp,
        private readonly ?BankAccount $transferAccount,
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
        $paypalConfigured =
            filled(
                config(
                    'paypal.client_id',
                ),
            );

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
                'paypal_enabled' => (bool) $this->paypal_enabled
                    && $paypalConfigured,

                'transfer_enabled' => (bool) $this->transfer_enabled
                    && $this->transferAccount !== null,

                'cash_enabled' => (bool) $this->cash_enabled,
            ],

            'whatsapp' => [
                'active' => (bool) $this->whatsapp->active,

                'phone' => $this->whatsapp->active
                        ? $this->whatsapp->phone
                        : null,
            ],
        ];
    }
}
