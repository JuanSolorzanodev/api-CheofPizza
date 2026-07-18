<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\WhatsAppSetting;
use Illuminate\Database\Seeder;

final class CommerceSeeder extends Seeder
{
    /**
     * Configuración comercial inicial opcional.
     *
     * Este seeder no contiene datos técnicos indispensables
     * para que la aplicación funcione.
     *
     * Más adelante, la cuenta bancaria y WhatsApp serán
     * administrados desde el panel administrativo.
     */
    public function run(): void
    {
        $this->seedBankAccount();
        $this->seedWhatsAppSetting();
    }

    private function seedBankAccount(): void
    {
        BankAccount::query()->updateOrCreate(
            [
                'bank_name' =>
                    'Banco Pichincha',

                'account_number' =>
                    '3337643104',
            ],
            [
                'active' =>
                    true,

                'priority' =>
                    1,

                'account_type' =>
                    'Corriente',

                'holder_name' =>
                    'Lenny Patricia Cedeño Rodriguez',

                'holder_id' =>
                    '1313173682',

                'qr_image_url' =>
                    'https://res.cloudinary.com/dertc9kiq/image/upload/v1774654039/466d1735-aa30-454b-83c0-08bbfb27bc23.png',
            ],
        );
    }

    private function seedWhatsAppSetting(): void
    {
        WhatsAppSetting::query()->updateOrCreate(
            [
                'id' => 1,
            ],
            [
                'active' =>
                    true,

                'phone' =>
                    '+593939917715',

                'receipt_template' =>
                    'Hola, envío el comprobante de transferencia del pedido {ORDER_NUMBER}. '
                    .'Total: {TOTAL}. '
                    .'Entrega: {DELIVERY_TYPE}. '
                    .'Dirección: {ADDRESS}.',
            ],
        );
    }
}
