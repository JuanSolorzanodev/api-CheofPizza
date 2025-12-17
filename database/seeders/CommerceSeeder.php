<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CartStatus;
use App\Models\DeliveryType;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;

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
    }
}
