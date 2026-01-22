<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\WhatsAppSetting;

class WhatsAppReceiptLinkService
{
    public function build(Order $order): ?string
    {
        $setting = WhatsAppSetting::query()
            ->where('active', true)
            ->orderBy('id')
            ->first();

        if (!$setting) return null;

        $phone = (string) $setting->phone;
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) return null;

        $deliveryType = (string) ($order->deliveryType?->delivery_type_name ?? '');
        $address = (string) ($order->address ?? '');

        $template = (string) ($setting->receipt_template ?: 'Hola, envío el comprobante del pedido {ORDER_NUMBER}. Total: {TOTAL}.');

        $text = strtr($template, [
            '{ORDER_NUMBER}' => (string) $order->order_number,
            '{TOTAL}' => number_format((float) $order->total, 2, '.', ''),
            '{DELIVERY_TYPE}' => $deliveryType !== '' ? $deliveryType : 'N/A',
            '{ADDRESS}' => $address !== '' ? $address : 'N/A',
        ]);

        $query = http_build_query(['text' => $text]);

        return "https://wa.me/{$digits}?{$query}";
    }
}
