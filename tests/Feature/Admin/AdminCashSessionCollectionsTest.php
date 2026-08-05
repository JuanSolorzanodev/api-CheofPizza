<?php

declare(strict_types=1);

use App\Enums\CashSessionStatus;
use App\Enums\PaymentReceiptStatus;
use App\Enums\PaymentStatus;
use App\Models\CashSession;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

it(
    'calculates collections for all successful payment methods during the cash session',
    function (): void {
        /** @var TestCase $this */
        CarbonImmutable::setTestNow(
            '2026-08-03 17:00:00'
        );

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $deliveredStatus =
            OrderStatus::query()
                ->firstOrCreate([
                    'status_name' => 'delivered',
                ]);

        $pickup =
            DeliveryType::query()
                ->firstOrCreate([
                    'delivery_type_name' => 'pickup',
                ]);

        $cashMethod =
            PaymentMethod::query()
                ->firstOrCreate(
                    ['name' => 'cash'],
                    [
                        'description' => 'Efectivo',

                        'active' => true,
                    ],
                );

        $transferMethod =
            PaymentMethod::query()
                ->firstOrCreate(
                    ['name' => 'transfer'],
                    [
                        'description' => 'Transferencia',

                        'active' => true,
                    ],
                );

        $cardMethod =
            PaymentMethod::query()
                ->firstOrCreate(
                    ['name' => 'card'],
                    [
                        'description' => 'PayPal',

                        'active' => true,
                    ],
                );

        $session =
            CashSession::query()
                ->create([
                    'uuid' => (string) Str::uuid(),

                    'opened_by' => $admin->id,

                    'status' => CashSessionStatus::Open,

                    'opening_amount' => '20.00',

                    'opened_at' => now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Pedido en efectivo
        |--------------------------------------------------------------------------
        */

        $cashOrder =
            Order::query()
                ->create([
                    'order_number' => 'CH-COLLECTION-CASH',

                    'user_id' => $customer->id,

                    'ordered_at' => '2026-08-03 17:10:00',

                    'subtotal' => '30.00',

                    'delivery_fee' => '0.00',

                    'total' => '30.00',

                    'delivery_type_id' => $pickup->id,

                    'payment_method_id' => $cashMethod->id,

                    'order_status_id' => $deliveredStatus->id,
                ]);

        OrderStatusChange::query()
            ->create([
                'order_id' => $cashOrder->id,

                'from_order_status_id' => null,

                'to_order_status_id' => $deliveredStatus->id,

                'changed_by_user_id' => $admin->id,

                'changed_at' => '2026-08-03 18:00:00',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Transferencia aprobada
        |--------------------------------------------------------------------------
        */

        $transferOrder =
            Order::query()
                ->create([
                    'order_number' => 'CH-COLLECTION-TRANSFER',

                    'user_id' => $customer->id,

                    'ordered_at' => '2026-08-03 17:20:00',

                    'subtotal' => '40.00',

                    'delivery_fee' => '0.00',

                    'total' => '40.00',

                    'delivery_type_id' => $pickup->id,

                    'payment_method_id' => $transferMethod->id,

                    'order_status_id' => $deliveredStatus->id,
                ]);

        PaymentReceipt::query()
            ->create([
                'uuid' => (string) Str::uuid(),

                'order_id' => $transferOrder->id,

                'user_id' => $customer->id,

                'disk' => 'payment_receipts',

                'file_path' => 'tests/transfer.jpg',

                'original_name' => 'transfer.jpg',

                'mime_type' => 'image/jpeg',

                'file_size' => 1024,

                'status' => PaymentReceiptStatus::Approved,

                'submitted_at' => '2026-08-03 18:10:00',

                'reviewed_at' => '2026-08-03 18:20:00',

                'reviewed_by' => $admin->id,
            ]);

        /*
        |--------------------------------------------------------------------------
        | PayPal completado
        |--------------------------------------------------------------------------
        */

        $paypalOrder =
            Order::query()
                ->create([
                    'order_number' => 'CH-COLLECTION-PAYPAL',

                    'user_id' => $customer->id,

                    'ordered_at' => '2026-08-03 17:30:00',

                    'subtotal' => '50.00',

                    'delivery_fee' => '0.00',

                    'total' => '50.00',

                    'delivery_type_id' => $pickup->id,

                    'payment_method_id' => $cardMethod->id,

                    'order_status_id' => $deliveredStatus->id,
                ]);

        Payment::query()
            ->create([
                'uuid' => (string) Str::uuid(),

                'idempotency_key' => (string) Str::uuid(),

                'user_id' => $customer->id,

                'order_id' => $paypalOrder->id,

                'provider' => 'paypal',

                'provider_order_id' => 'PAYPAL-COLLECTION-ORDER',

                'provider_capture_id' => 'PAYPAL-COLLECTION-CAPTURE',

                'provider_status' => 'COMPLETED',

                'amount' => '50.00',

                'currency' => 'USD',

                'status' => PaymentStatus::COMPLETED,

                'paid_at' => '2026-08-03 18:30:00',
            ]);

        CarbonImmutable::setTestNow(
            '2026-08-03 19:00:00'
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/cash-register/{$session->uuid}/summary"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.amounts.opening_amount',
                20,
            )
            ->assertJsonPath(
                'data.amounts.cash_sales',
                30,
            )
            ->assertJsonPath(
                'data.amounts.expected_cash',
                50,
            )
            ->assertJsonPath(
                'data.collections.cash.amount',
                30,
            )
            ->assertJsonPath(
                'data.collections.cash.transactions',
                1,
            )
            ->assertJsonPath(
                'data.collections.transfer.amount',
                40,
            )
            ->assertJsonPath(
                'data.collections.transfer.transactions',
                1,
            )
            ->assertJsonPath(
                'data.collections.paypal.amount',
                50,
            )
            ->assertJsonPath(
                'data.collections.paypal.transactions',
                1,
            )
            ->assertJsonPath(
                'data.collections.total_collected',
                120,
            )
            ->assertJsonPath(
                'data.activity.collected_transactions',
                3,
            );

        CarbonImmutable::setTestNow();
    },
);

it(
    'excludes pending and out of session electronic payments',
    function (): void {
        /** @var TestCase $this */
        CarbonImmutable::setTestNow(
            '2026-08-03 17:00:00'
        );

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $pickup =
            DeliveryType::query()
                ->firstOrCreate([
                    'delivery_type_name' => 'pickup',
                ]);

        $status =
            OrderStatus::query()
                ->firstOrCreate([
                    'status_name' => 'delivered',
                ]);

        $transferMethod =
            PaymentMethod::query()
                ->firstOrCreate(
                    ['name' => 'transfer'],
                    [
                        'description' => 'Transferencia',

                        'active' => true,
                    ],
                );

        $cardMethod =
            PaymentMethod::query()
                ->firstOrCreate(
                    ['name' => 'card'],
                    [
                        'description' => 'PayPal',

                        'active' => true,
                    ],
                );

        $session =
            CashSession::query()
                ->create([
                    'uuid' => (string) Str::uuid(),

                    'opened_by' => $admin->id,

                    'status' => CashSessionStatus::Open,

                    'opening_amount' => '10.00',

                    'opened_at' => now(),
                ]);

        $pendingTransfer =
            Order::query()
                ->create([
                    'order_number' => 'CH-PENDING-TRANSFER',

                    'user_id' => $customer->id,

                    'ordered_at' => '2026-08-03 17:10:00',

                    'subtotal' => '25.00',

                    'delivery_fee' => '0.00',

                    'total' => '25.00',

                    'delivery_type_id' => $pickup->id,

                    'payment_method_id' => $transferMethod->id,

                    'order_status_id' => $status->id,
                ]);

        PaymentReceipt::query()
            ->create([
                'uuid' => (string) Str::uuid(),

                'order_id' => $pendingTransfer->id,

                'user_id' => $customer->id,

                'disk' => 'payment_receipts',

                'file_path' => 'tests/pending.jpg',

                'original_name' => 'pending.jpg',

                'mime_type' => 'image/jpeg',

                'file_size' => 512,

                'status' => PaymentReceiptStatus::Pending,

                'submitted_at' => '2026-08-03 18:00:00',
            ]);

        Payment::query()
            ->create([
                'uuid' => (string) Str::uuid(),

                'idempotency_key' => (string) Str::uuid(),

                'user_id' => $customer->id,

                'provider' => 'paypal',

                'provider_order_id' => 'PAYPAL-PENDING',

                'provider_status' => 'APPROVED',

                'amount' => '35.00',

                'currency' => 'USD',

                'status' => PaymentStatus::APPROVED,

                'approved_at' => '2026-08-03 18:20:00',
            ]);

        Payment::query()
            ->create([
                'uuid' => (string) Str::uuid(),

                'idempotency_key' => (string) Str::uuid(),

                'user_id' => $customer->id,

                'provider' => 'paypal',

                'provider_order_id' => 'PAYPAL-OUTSIDE',

                'provider_capture_id' => 'CAPTURE-OUTSIDE',

                'provider_status' => 'COMPLETED',

                'amount' => '60.00',

                'currency' => 'USD',

                'status' => PaymentStatus::COMPLETED,

                'paid_at' => '2026-08-03 16:30:00',
            ]);

        CarbonImmutable::setTestNow(
            '2026-08-03 19:00:00'
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/cash-register/{$session->uuid}/summary"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.collections.transfer.amount',
                0,
            )
            ->assertJsonPath(
                'data.collections.transfer.transactions',
                0,
            )
            ->assertJsonPath(
                'data.collections.paypal.amount',
                0,
            )
            ->assertJsonPath(
                'data.collections.paypal.transactions',
                0,
            )
            ->assertJsonPath(
                'data.collections.total_collected',
                0,
            )
            ->assertJsonPath(
                'data.amounts.expected_cash',
                10,
            );

        CarbonImmutable::setTestNow();
    },
);
