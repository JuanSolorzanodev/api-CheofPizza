<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\BusinessSetting;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BusinessSettingService
{
    private const CACHE_KEY =
        'business-settings.current';

    public function current(
        bool $useCache = true,
    ): BusinessSetting {
        if (
            ! $useCache
            || app()->environment('testing')
            || app()->runningUnitTests()
        ) {
            return $this->findOrCreate();
        }

        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            fn (): BusinessSetting =>
                $this->findOrCreate(),
        );
    }

    public function whatsapp(): WhatsAppSetting
    {
        $setting = WhatsAppSetting::query()
            ->first();

        if ($setting !== null) {
            return $setting;
        }

        return WhatsAppSetting::query()
            ->create([
                'active' => false,
                'phone' => null,
                'receipt_template' =>
                    'Hola, adjunto el comprobante de mi pedido.',
            ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(
        array $payload,
    ): BusinessSetting {
        $setting = DB::transaction(
            function () use (
                $payload,
            ): BusinessSetting {
                $setting = BusinessSetting::query()
                    ->lockForUpdate()
                    ->first();

                if ($setting === null) {
                    $setting = BusinessSetting::query()
                        ->create(
                            BusinessSetting::defaultValues(),
                        );
                }

                $business = $payload['business'];
                $store = $payload['store'];
                $delivery = $payload['delivery'];
                $payments = $payload['payments'];
                $whatsapp = $payload['whatsapp'];

                $setting->fill([
                    'business_name' =>
                        $business['name'],

                    'phone' =>
                        $business['phone'],

                    'email' =>
                        $business['email'],

                    'address' =>
                        $business['address'],

                    'accepts_orders' =>
                        $store['accepts_orders'],

                    'closed_message' =>
                        $store['closed_message'],

                    'estimated_minutes' =>
                        $store['estimated_minutes'],

                    'currency' =>
                        $store['currency'],

                    'timezone' =>
                        $store['timezone'],

                    'pickup_enabled' =>
                        $delivery['pickup_enabled'],

                    'delivery_enabled' =>
                        $delivery['delivery_enabled'],

                    'delivery_fee' =>
                        $delivery['delivery_fee'],

                    'minimum_order' =>
                        $delivery['minimum_order'],

                    'paypal_enabled' =>
                        $payments['paypal_enabled'],

                    'transfer_enabled' =>
                        $payments['transfer_enabled'],

                    'cash_enabled' =>
                        $payments['cash_enabled'],
                ])->save();

                $whatsappSetting =
                    WhatsAppSetting::query()
                        ->lockForUpdate()
                        ->first();

                if ($whatsappSetting === null) {
                    $whatsappSetting =
                        new WhatsAppSetting();
                }

                $whatsappSetting->fill([
                    'active' =>
                        $whatsapp['active'],

                    'phone' =>
                        $whatsapp['phone'],

                    'receipt_template' =>
                        $whatsapp['receipt_template'],
                ])->save();

                return $setting->refresh();
            },
            attempts: 3,
        );

        $this->clearCache();

        return $setting;
    }

    public function clearCache(): void
    {
        Cache::forget(
            self::CACHE_KEY,
        );
    }

    private function findOrCreate(): BusinessSetting
    {
        $setting = BusinessSetting::query()
            ->first();

        if ($setting !== null) {
            return $setting;
        }

        return BusinessSetting::query()
            ->create(
                BusinessSetting::defaultValues(),
            );
    }
}
