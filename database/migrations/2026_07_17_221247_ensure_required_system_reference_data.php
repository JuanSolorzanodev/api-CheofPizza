<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Datos técnicos indispensables para que la aplicación funcione.
     *
     * No son contenido comercial ni datos de demostración.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->ensureRoles();
            $this->ensureCartStatuses();
            $this->ensureOrderStatuses();
            $this->ensureDeliveryTypes();
            $this->ensurePaymentMethods();
            $this->ensurePersonalizationActions();
        });
    }

    /**
     * No eliminamos estos registros durante rollback porque pueden
     * estar relacionados con usuarios, carritos, pedidos y pagos reales.
     */
    public function down(): void
    {
        // Intencionalmente vacío.
    }

    private function ensureRoles(): void
    {
        foreach (
            [
                'admin',
                'customer',
                'operator',
            ] as $roleName
        ) {
            $exists = DB::table('roles')
                ->where('role_name', $roleName)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('roles')->insert([
                'role_name' => $roleName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureCartStatuses(): void
    {
        foreach (
            [
                'active',
                'ordered',
                'abandoned',
            ] as $statusName
        ) {
            $exists = DB::table('cart_statuses')
                ->where('status_name', $statusName)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('cart_statuses')->insert([
                'status_name' => $statusName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureOrderStatuses(): void
    {
        foreach (
            [
                'pending',
                'confirmed',
                'preparing',
                'ready',
                'on_the_way',
                'delivered',
                'cancelled',
            ] as $statusName
        ) {
            $exists = DB::table('order_statuses')
                ->where('status_name', $statusName)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('order_statuses')->insert([
                'status_name' => $statusName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureDeliveryTypes(): void
    {
        foreach (
            [
                'delivery',
                'pickup',
            ] as $deliveryTypeName
        ) {
            $exists = DB::table('delivery_types')
                ->where(
                    'delivery_type_name',
                    $deliveryTypeName,
                )
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('delivery_types')->insert([
                'delivery_type_name' =>
                    $deliveryTypeName,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
        }
    }

    private function ensurePaymentMethods(): void
    {
        $paymentMethods = [
            [
                'name' => 'cash',
                'description' => 'Pago en efectivo',
                'active' => true,
            ],
            [
                'name' => 'transfer',
                'description' => 'Transferencia bancaria',
                'active' => true,
            ],
            [
                'name' => 'card',
                'description' => 'Tarjeta mediante PayPal',
                'active' => true,
            ],
        ];

        foreach ($paymentMethods as $paymentMethod) {
            DB::table('payment_methods')->updateOrInsert(
                [
                    'name' =>
                        $paymentMethod['name'],
                ],
                [
                    'description' =>
                        $paymentMethod['description'],

                    'active' =>
                        $paymentMethod['active'],
                ],
            );
        }
    }

    private function ensurePersonalizationActions(): void
    {
        $actions = [
            [
                'action_name' => 'Agregar',
                'description' => 'Añadir ingrediente',
            ],
            [
                'action_name' => 'Quitar',
                'description' => 'Eliminar ingrediente',
            ],
            [
                'action_name' => 'Extra',
                'description' => 'Porción extra del ingrediente',
            ],
        ];

        foreach ($actions as $action) {
            $existingId = DB::table(
                'personalization_actions',
            )
                ->where(
                    'action_name',
                    $action['action_name'],
                )
                ->value('id');

            if ($existingId !== null) {
                DB::table('personalization_actions')
                    ->where('id', $existingId)
                    ->update([
                        'description' =>
                            $action['description'],

                        'updated_at' =>
                            now(),
                    ]);

                continue;
            }

            DB::table('personalization_actions')
                ->insert([
                    'action_name' =>
                        $action['action_name'],

                    'description' =>
                        $action['description'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }
    }
};
