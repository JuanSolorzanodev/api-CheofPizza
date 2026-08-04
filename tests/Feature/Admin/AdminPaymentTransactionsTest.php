<?php

declare(strict_types=1);

use App\Enums\PaymentReceiptStatus;
use App\Enums\PaymentStatus;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Crea o recupera los catálogos mínimos utilizados
 * por las pruebas del módulo de transacciones.
 *
 * @return array{
 *     delivered_status_id: int,
 *     delivery_type_id: int,
 *     cash_method_id: int,
 *     transfer_method_id: int,
 *     card_method_id: int
 * }
 */
function paymentTransactionCatalog(): array
{
    $deliveredStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' => 'delivered',
        ]);

    $deliveryType = DeliveryType::query()
        ->firstOrCreate([
            'delivery_type_name' => 'pickup',
        ]);

    $cash = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' => 'cash',
            ],
            [
                'description' => 'Efectivo',
                'active' => true,
            ],
        );

    $transfer = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' => 'transfer',
            ],
            [
                'description' => 'Transferencia',
                'active' => true,
            ],
        );

    $card = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' => 'card',
            ],
            [
                'description' => 'PayPal',
                'active' => true,
            ],
        );

    return [
        'delivered_status_id' =>
            (int) $deliveredStatus->id,

        'delivery_type_id' =>
            (int) $deliveryType->id,

        'cash_method_id' =>
            (int) $cash->id,

        'transfer_method_id' =>
            (int) $transfer->id,

        'card_method_id' =>
            (int) $card->id,
    ];
}

/**
 * Crea un pedido reutilizable para las pruebas.
 *
 * @param array<string, int> $catalog
 */
function paymentTransactionOrder(
    User $customer,
    array $catalog,
    string $number,
    int $paymentMethodId,
    string $total,
    string $orderedAt = '2026-08-01 18:00:00',
): Order {
    return Order::query()->create([
        'order_number' => $number,
        'user_id' => $customer->id,
        'ordered_at' => $orderedAt,
        'subtotal' => $total,
        'delivery_fee' => '0.00',
        'total' => $total,

        'delivery_type_id' =>
            $catalog['delivery_type_id'],

        'payment_method_id' =>
            $paymentMethodId,

        'order_status_id' =>
            $catalog['delivered_status_id'],
    ]);
}

it(
    'requires authentication for payment transactions',
    function (): void {
        /** @var TestCase $this */

        $this
            ->getJson(
                '/api/v1/admin/analytics/payment-transactions'
            )
            ->assertUnauthorized();
    },
);

it(
    'returns unified cash transfer and paypal transactions with global summary',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create([
                'first_name' => 'Ana',
                'last_name' => 'Torres',
                'email' => 'ana@example.com',
                'phone' => '0991112222',
            ]);

        $catalog =
            paymentTransactionCatalog();

        /*
        |--------------------------------------------------------------------------
        | Cobro en efectivo
        |--------------------------------------------------------------------------
        */

        $cashOrder =
            paymentTransactionOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-TX-CASH',
                paymentMethodId:
                    $catalog['cash_method_id'],
                total: '20.00',
            );

        OrderStatusChange::query()->create([
            'order_id' => $cashOrder->id,

            'from_order_status_id' =>
                null,

            'to_order_status_id' =>
                $catalog['delivered_status_id'],

            'changed_by_user_id' =>
                $admin->id,

            'changed_at' =>
                '2026-08-01 18:30:00',

            'note' =>
                'Cobro en efectivo',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Transferencia aprobada
        |--------------------------------------------------------------------------
        */

        $transferOrder =
            paymentTransactionOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-TX-TRANSFER',
                paymentMethodId:
                    $catalog['transfer_method_id'],
                total: '30.00',
            );

        PaymentReceipt::query()->create([
            'uuid' =>
                (string) Str::uuid(),

            'order_id' =>
                $transferOrder->id,

            'user_id' =>
                $customer->id,

            'disk' =>
                'payment_receipts',

            'file_path' =>
                'tests/transfer.jpg',

            'original_name' =>
                'transfer.jpg',

            'mime_type' =>
                'image/jpeg',

            'file_size' =>
                1024,

            'status' =>
                PaymentReceiptStatus::Approved,

            'submitted_at' =>
                '2026-08-01 19:00:00',

            'reviewed_at' =>
                '2026-08-01 19:15:00',

            'reviewed_by' =>
                $admin->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | PayPal completado
        |--------------------------------------------------------------------------
        */

        $paypalOrder =
            paymentTransactionOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-TX-PAYPAL',
                paymentMethodId:
                    $catalog['card_method_id'],
                total: '40.00',
            );

        Payment::query()->create([
            'uuid' =>
                (string) Str::uuid(),

            'idempotency_key' =>
                (string) Str::uuid(),

            'user_id' =>
                $customer->id,

            'order_id' =>
                $paypalOrder->id,

            'provider' =>
                'paypal',

            'provider_order_id' =>
                'PAYPAL-TX-ORDER-001',

            'provider_capture_id' =>
                'PAYPAL-TX-CAPTURE-001',

            'provider_status' =>
                'COMPLETED',

            'amount' =>
                '40.00',

            'currency' =>
                'USD',

            'status' =>
                PaymentStatus::COMPLETED,

            'paid_at' =>
                '2026-08-01 20:00:00',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payment-transactions'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
                    . '&per_page=15'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true,
            )
            ->assertJsonPath(
                'meta.total',
                3,
            )
            ->assertJsonCount(
                3,
                'data.transactions',
            )

            /*
            |--------------------------------------------------------------------------
            | Orden de resultados
            |--------------------------------------------------------------------------
            */

            ->assertJsonPath(
                'data.transactions.0.method',
                'paypal',
            )
            ->assertJsonPath(
                'data.transactions.0.amount',
                40,
            )
            ->assertJsonPath(
                'data.transactions.1.method',
                'transfer',
            )
            ->assertJsonPath(
                'data.transactions.1.status',
                'approved',
            )
            ->assertJsonPath(
                'data.transactions.2.method',
                'cash',
            )
            ->assertJsonPath(
                'data.transactions.2.status',
                'collected',
            )

            /*
            |--------------------------------------------------------------------------
            | Volumen total procesado
            |--------------------------------------------------------------------------
            */

            ->assertJsonPath(
                'data.summary.volume.amount',
                90,
            )
            ->assertJsonPath(
                'data.summary.volume.transactions',
                3,
            )

            /*
            |--------------------------------------------------------------------------
            | Recaudación reconocida
            |--------------------------------------------------------------------------
            */

            ->assertJsonPath(
                'data.summary.collected.amount',
                90,
            )
            ->assertJsonPath(
                'data.summary.collected.transactions',
                3,
            )

            /*
            |--------------------------------------------------------------------------
            | Desglose por método
            |--------------------------------------------------------------------------
            */

            ->assertJsonPath(
                'data.summary.methods.cash.amount',
                20,
            )
            ->assertJsonPath(
                'data.summary.methods.cash.transactions',
                1,
            )
            ->assertJsonPath(
                'data.summary.methods.transfer.amount',
                30,
            )
            ->assertJsonPath(
                'data.summary.methods.transfer.transactions',
                1,
            )
            ->assertJsonPath(
                'data.summary.methods.paypal.amount',
                40,
            )
            ->assertJsonPath(
                'data.summary.methods.paypal.transactions',
                1,
            )

            /*
            |--------------------------------------------------------------------------
            | Operaciones pendientes o no exitosas
            |--------------------------------------------------------------------------
            */

            ->assertJsonPath(
                'data.summary.pending.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending.transactions',
                0,
            )
            ->assertJsonPath(
                'data.summary.unsuccessful.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.unsuccessful.transactions',
                0,
            );
    },
);

it(
    'filters payment transactions by method status and search',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create([
                'first_name' => 'Carlos',
                'last_name' => 'Mora',
                'email' =>
                    'carlos.mora@example.com',

                'phone' =>
                    '0987654321',
            ]);

        $catalog =
            paymentTransactionCatalog();

        $order =
            paymentTransactionOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-SEARCH-001',
                paymentMethodId:
                    $catalog['transfer_method_id'],
                total: '25.00',
            );

        $receipt =
            PaymentReceipt::query()->create([
                'uuid' =>
                    (string) Str::uuid(),

                'order_id' =>
                    $order->id,

                'user_id' =>
                    $customer->id,

                'disk' =>
                    'payment_receipts',

                'file_path' =>
                    'tests/pending.jpg',

                'original_name' =>
                    'pending.jpg',

                'mime_type' =>
                    'image/jpeg',

                'file_size' =>
                    512,

                'status' =>
                    PaymentReceiptStatus::Pending,

                'submitted_at' =>
                    '2026-08-01 21:00:00',
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payment-transactions'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&method=transfer'
                    . '&status=pending'
                    . '&search='
                    . urlencode('Carlos Mora')
                    . '&per_page=15'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.transactions.0.method',
                'transfer',
            )
            ->assertJsonPath(
                'data.transactions.0.status',
                'pending',
            )
            ->assertJsonPath(
                'data.transactions.0.order_number',
                'CH-SEARCH-001',
            )
            ->assertJsonPath(
                'data.transactions.0.receipt_uuid',
                $receipt->uuid,
            )

            /*
            |--------------------------------------------------------------------------
            | El resumen respeta los mismos filtros
            |--------------------------------------------------------------------------
            */

            ->assertJsonPath(
                'data.summary.volume.amount',
                25,
            )
            ->assertJsonPath(
                'data.summary.volume.transactions',
                1,
            )
            ->assertJsonPath(
                'data.summary.collected.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.collected.transactions',
                0,
            )
            ->assertJsonPath(
                'data.summary.methods.cash.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.methods.transfer.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.methods.paypal.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending.amount',
                25,
            )
            ->assertJsonPath(
                'data.summary.pending.transactions',
                1,
            )
            ->assertJsonPath(
                'data.summary.unsuccessful.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.unsuccessful.transactions',
                0,
            );
    },
);

it(
    'calculates unsuccessful transactions separately from collected revenue',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create([
                'first_name' => 'Lucía',
                'last_name' => 'Vera',
            ]);

        $catalog =
            paymentTransactionCatalog();

        $order =
            paymentTransactionOrder(
                customer: $customer,
                catalog: $catalog,
                number: 'CH-REJECTED-001',
                paymentMethodId:
                    $catalog['transfer_method_id'],
                total: '35.00',
            );

        PaymentReceipt::query()->create([
            'uuid' =>
                (string) Str::uuid(),

            'order_id' =>
                $order->id,

            'user_id' =>
                $customer->id,

            'disk' =>
                'payment_receipts',

            'file_path' =>
                'tests/rejected.jpg',

            'original_name' =>
                'rejected.jpg',

            'mime_type' =>
                'image/jpeg',

            'file_size' =>
                1024,

            'status' =>
                PaymentReceiptStatus::Rejected,

            'submitted_at' =>
                '2026-08-01 17:30:00',

            'reviewed_at' =>
                '2026-08-01 18:00:00',

            'reviewed_by' =>
                $admin->id,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payment-transactions'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&timezone=America/Guayaquil'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonPath(
                'data.transactions.0.status',
                'rejected',
            )
            ->assertJsonPath(
                'data.summary.volume.amount',
                35,
            )
            ->assertJsonPath(
                'data.summary.volume.transactions',
                1,
            )
            ->assertJsonPath(
                'data.summary.collected.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.collected.transactions',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending.transactions',
                0,
            )
            ->assertJsonPath(
                'data.summary.unsuccessful.amount',
                35,
            )
            ->assertJsonPath(
                'data.summary.unsuccessful.transactions',
                1,
            );
    },
);

it(
    'returns a global summary independent from pagination',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog =
            paymentTransactionCatalog();

        for (
            $index = 1;
            $index <= 12;
            $index++
        ) {
            $order =
                paymentTransactionOrder(
                    customer: $customer,
                    catalog: $catalog,
                    number: sprintf(
                        'CH-PAGE-%03d',
                        $index,
                    ),
                    paymentMethodId:
                        $catalog['cash_method_id'],
                    total: '10.00',
                );

            OrderStatusChange::query()->create([
                'order_id' =>
                    $order->id,

                'from_order_status_id' =>
                    null,

                'to_order_status_id' =>
                    $catalog[
                        'delivered_status_id'
                    ],

                'changed_by_user_id' =>
                    $admin->id,

                'changed_at' =>
                    sprintf(
                        '2026-08-01 18:%02d:00',
                        $index,
                    ),
            ]);
        }

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payment-transactions'
                    . '?date_from=2026-08-01'
                    . '&date_to=2026-08-01'
                    . '&page=2'
                    . '&per_page=10'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.current_page',
                2,
            )
            ->assertJsonPath(
                'meta.per_page',
                10,
            )
            ->assertJsonPath(
                'meta.total',
                12,
            )
            ->assertJsonPath(
                'meta.last_page',
                2,
            )
            ->assertJsonCount(
                2,
                'data.transactions',
            )

            /*
            |--------------------------------------------------------------------------
            | Aunque la página contiene solo dos filas,
            | el resumen incluye las doce transacciones.
            |--------------------------------------------------------------------------
            */

            ->assertJsonPath(
                'data.summary.volume.amount',
                120,
            )
            ->assertJsonPath(
                'data.summary.volume.transactions',
                12,
            )
            ->assertJsonPath(
                'data.summary.collected.amount',
                120,
            )
            ->assertJsonPath(
                'data.summary.collected.transactions',
                12,
            )
            ->assertJsonPath(
                'data.summary.methods.cash.amount',
                120,
            )
            ->assertJsonPath(
                'data.summary.methods.cash.transactions',
                12,
            )
            ->assertJsonPath(
                'data.summary.methods.transfer.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.methods.transfer.transactions',
                0,
            )
            ->assertJsonPath(
                'data.summary.methods.paypal.amount',
                0,
            )
            ->assertJsonPath(
                'data.summary.methods.paypal.transactions',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending.transactions',
                0,
            )
            ->assertJsonPath(
                'data.summary.unsuccessful.transactions',
                0,
            );
    },
);

it(
    'validates payment transaction filters',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs(
                $admin,
                'sanctum',
            )
            ->getJson(
                '/api/v1/admin/analytics/payment-transactions'
                    . '?method=bitcoin'
                    . '&status=unknown'
                    . '&per_page=12'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'method',
                'status',
                'per_page',
            ]);
    },
);
