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
    'requires authentication to view a cash session summary',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $session = CashSession::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'opened_by' =>
                    $admin->id,

                'status' =>
                    CashSessionStatus::Open,

                'opening_amount' =>
                    '50.00',

                'opened_at' =>
                    now(),
            ]);

        $this
            ->getJson(
                "/api/v1/admin/cash-register/{$session->uuid}/summary"
            )
            ->assertUnauthorized();
    },
);

it(
    'returns the live cash session summary',
    function (): void {
        /** @var TestCase $this */

        /*
         * La sesión empieza a las 17:00.
         */
        CarbonImmutable::setTestNow(
            '2026-08-03 17:00:00'
        );

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $deliveredStatus = OrderStatus::query()
            ->firstOrCreate([
                'status_name' =>
                    'delivered',
            ]);

        $deliveryType = DeliveryType::query()
            ->firstOrCreate([
                'delivery_type_name' =>
                    'pickup',
            ]);

        $cashMethod = PaymentMethod::query()
            ->firstOrCreate(
                [
                    'name' =>
                        'cash',
                ],
                [
                    'description' =>
                        'Efectivo',

                    'active' =>
                        true,
                ],
            );

        $session = CashSession::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'opened_by' =>
                    $admin->id,

                'status' =>
                    CashSessionStatus::Open,

                'opening_amount' =>
                    '50.00',

                'opened_at' =>
                    now(),
            ]);

        $order = Order::query()
            ->create([
                'order_number' =>
                    'CH-SUMMARY-001',

                'user_id' =>
                    $customer->id,

                'ordered_at' =>
                    '2026-08-03 17:30:00',

                'subtotal' =>
                    '30.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '30.00',

                'delivery_type_id' =>
                    $deliveryType->id,

                'payment_method_id' =>
                    $cashMethod->id,

                'order_status_id' =>
                    $deliveredStatus->id,
            ]);

        OrderStatusChange::query()
            ->create([
                'order_id' =>
                    $order->id,

                'from_order_status_id' =>
                    null,

                'to_order_status_id' =>
                    $deliveredStatus->id,

                'changed_by_user_id' =>
                    $admin->id,

                'changed_at' =>
                    '2026-08-03 18:00:00',
            ]);

        CashMovement::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'cash_session_id' =>
                    $session->id,

                'created_by' =>
                    $admin->id,

                'type' =>
                    CashMovementType::Income,

                'amount' =>
                    '10.00',

                'reason' =>
                    'Cambio adicional',

                'occurred_at' =>
                    '2026-08-03 18:10:00',
            ]);

        CashMovement::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'cash_session_id' =>
                    $session->id,

                'created_by' =>
                    $admin->id,

                'type' =>
                    CashMovementType::Expense,

                'amount' =>
                    '4.00',

                'reason' =>
                    'Compra operativa',

                'occurred_at' =>
                    '2026-08-03 18:20:00',
            ]);

        /*
         * La caja sigue abierta y el servicio usa now() como límite.
         * Avanzamos el reloj para incluir la venta y los movimientos.
         */
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
                'data.session.status',
                'open',
            )
            ->assertJsonPath(
                'data.amounts.opening_amount',
                50,
            )
            ->assertJsonPath(
                'data.amounts.cash_sales',
                30,
            )
            ->assertJsonPath(
                'data.amounts.manual_income',
                10,
            )
            ->assertJsonPath(
                'data.amounts.manual_expense',
                4,
            )
            ->assertJsonPath(
                'data.amounts.expected_cash',
                86,
            )
            ->assertJsonPath(
                'data.activity.cash_orders',
                1,
            )
            ->assertJsonPath(
                'data.activity.income_movements',
                1,
            )
            ->assertJsonPath(
                'data.activity.expense_movements',
                1,
            )
            ->assertJsonPath(
                'data.activity.movements_total',
                2,
            );

        CarbonImmutable::setTestNow();
    },
);

it(
    'keeps the stored closing values for a closed session summary',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $session = CashSession::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'opened_by' =>
                    $admin->id,

                'closed_by' =>
                    $admin->id,

                'status' =>
                    CashSessionStatus::Closed,

                'opening_amount' =>
                    '50.00',

                'expected_cash' =>
                    '80.00',

                'counted_cash' =>
                    '79.00',

                'difference' =>
                    '-1.00',

                'opened_at' =>
                    now()->subHours(5),

                'closed_at' =>
                    now(),
            ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/cash-register/{$session->uuid}/summary"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.session.status',
                'closed',
            )
            ->assertJsonPath(
                'data.amounts.counted_cash',
                79,
            )
            ->assertJsonPath(
                'data.amounts.difference',
                -1,
            );
    },
);
