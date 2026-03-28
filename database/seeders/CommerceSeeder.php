<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CartStatus;
use App\Models\DeliveryType;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\BankAccount;
use App\Models\WhatsAppSetting;

class CommerceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cart statuses
        foreach (['active', 'ordered', 'abandoned'] as $name) {
            CartStatus::updateOrCreate(['status_name' => $name], []);
        }

        // Order statuses
        $orderStatuses = [
            'pending',
            'confirmed',
            'preparing',
            'ready',
            'on_the_way',
            'delivered',
            'cancelled',
        ];
        foreach ($orderStatuses as $name) {
            OrderStatus::updateOrCreate(['status_name' => $name], []);
        }

        // Delivery types
        foreach (['delivery', 'pickup'] as $name) {
            DeliveryType::updateOrCreate(['delivery_type_name' => $name], []);
        }

        // Payment methods (tabla sin timestamps, tu modelo ya tiene $timestamps=false)
        $methods = [
            ['name' => 'cash', 'description' => 'Pago en efectivo', 'active' => true],
            ['name' => 'transfer', 'description' => 'Transferencia bancaria', 'active' => true],
            ['name' => 'card', 'description' => 'Tarjeta', 'active' => true],
        ];

        foreach ($methods as $m) {
            PaymentMethod::updateOrCreate(
                ['name' => $m['name']],
                ['description' => $m['description'], 'active' => $m['active']]
            );
        }


        BankAccount::updateOrCreate(
            ['bank_name' => 'Banco Pichincha', 'account_number' => '3337643104'],
            [
                'active' => true,
                'priority' => 1,
                'account_type' => 'Corriente',
                'holder_name' => 'Lenny Patricia Cedeño Rodriguez',
                'holder_id' => '1313173682',
                'qr_image_url' => 'https://res.cloudinary.com/dertc9kiq/image/upload/v1774654039/466d1735-aa30-454b-83c0-08bbfb27bc23.png',
            ]
        );
        // WhatsApp settings (operativo para validar transferencias)
        WhatsAppSetting::updateOrCreate(
            ['id' => 1],
            [
                'active' => true,
                'phone' => '+593939917715',
                'receipt_template' =>
                'Hola, envío el comprobante de transferencia del pedido {ORDER_NUMBER}. Total: {TOTAL}. ' .
                    'Entrega: {DELIVERY_TYPE}. Dirección: {ADDRESS}.',
            ]
        );
    }
}
