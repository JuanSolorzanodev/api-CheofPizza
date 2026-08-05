<?php

declare(strict_types=1);

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

it(
    'returns the complete cash session detail',
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
            ->create([
                'first_name' => 'Ana',
                'last_name' => 'Torres',
            ]);

        $delivered = OrderStatus::query()
            ->firstOrCreate([
                'status_name' => 'delivered',
            ]);

        $pickup = DeliveryType::query()
            ->firstOrCreate([
                'delivery_type_name' => 'pickup',
            ]);

        $cash = PaymentMethod::query()
            ->firstOrCreate(
                ['name' => 'cash'],
                [
                    'description' => 'Efectivo',
                    'active' => true,
                ],
            );

        $session = CashSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'opened_by' => $admin->id,
            'status' => CashSessionStatus::Open,
            'opening_amount' => '50.00',
            'opened_at' => now(),
        ]);

        $order = Order::query()->create([
            'order_number' => 'CH-DETAIL-001',
            'user_id' => $customer->id,
            'ordered_at' => '2026-08-03 17:30:00',
            'subtotal' => '30.00',
            'delivery_fee' => '0.00',
            'total' => '30.00',
            'delivery_type_id' => $pickup->id,
            'payment_method_id' => $cash->id,
            'order_status_id' => $delivered->id,
        ]);

        OrderStatusChange::query()->create([
            'order_id' => $order->id,
            'from_order_status_id' => null,
            'to_order_status_id' => $delivered->id,
            'changed_by_user_id' => $admin->id,
            'changed_at' => '2026-08-03 18:00:00',
        ]);

        CashMovement::query()->create([
            'uuid' => (string) Str::uuid(),
            'cash_session_id' => $session->id,
            'created_by' => $admin->id,
            'type' => CashMovementType::Income,
            'amount' => '5.00',
            'reason' => 'Cambio adicional',
            'occurred_at' => '2026-08-03 18:10:00',
        ]);

        CarbonImmutable::setTestNow(
            '2026-08-03 19:00:00'
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/cash-register/{$session->uuid}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.session.status',
                'open',
            )
            ->assertJsonPath(
                'data.summary.amounts.cash_sales',
                30,
            )
            ->assertJsonPath(
                'data.summary.amounts.expected_cash',
                85,
            )
            ->assertJsonCount(
                1,
                'data.cash_orders',
            )
            ->assertJsonPath(
                'data.cash_orders.0.order_number',
                'CH-DETAIL-001',
            )
            ->assertJsonPath(
                'data.cash_orders.0.customer.name',
                'Ana Torres',
            )
            ->assertJsonCount(
                1,
                'data.movements',
            );

        CarbonImmutable::setTestNow();
    },
);

it(
    'filters the cash session history',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        CashSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'opened_by' => $admin->id,
            'closed_by' => $admin->id,
            'status' => CashSessionStatus::Closed,
            'opening_amount' => '20.00',
            'expected_cash' => '20.00',
            'counted_cash' => '20.00',
            'difference' => '0.00',
            'opened_at' => '2026-08-01 09:00:00',
            'closed_at' => '2026-08-01 18:00:00',
        ]);

        CashSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'opened_by' => $admin->id,
            'status' => CashSessionStatus::Open,
            'opening_amount' => '30.00',
            'opened_at' => '2026-08-03 09:00:00',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/v1/admin/cash-register/history'
                    .'?date_from=2026-08-01'
                    .'&date_to=2026-08-01'
                    .'&status=closed'
                    .'&per_page=15'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonCount(
                1,
                'data',
            )
            ->assertJsonPath(
                'data.0.status',
                'closed',
            );
    },
);

it(
    'validates cash session history filters',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/v1/admin/cash-register/history'
                    .'?date_from=2026-08-03'
                    .'&date_to=2026-08-01'
                    .'&status=invalid'
                    .'&per_page=12'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
                'status',
                'per_page',
            ]);
    },
);
