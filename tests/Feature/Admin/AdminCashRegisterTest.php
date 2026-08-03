<?php

declare(strict_types=1);

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusChange;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

function cashRegisterCatalog(): array
{
    return [
        'delivered_status_id' =>
            (int) OrderStatus::query()
                ->firstOrCreate([
                    'status_name' =>
                        'delivered',
                ])
                ->id,

        'delivery_type_id' =>
            (int) DeliveryType::query()
                ->firstOrCreate([
                    'delivery_type_name' =>
                        'pickup',
                ])
                ->id,

        'cash_method_id' =>
            (int) PaymentMethod::query()
                ->firstOrCreate(
                    ['name' => 'cash'],
                    [
                        'description' =>
                            'Efectivo',
                        'active' => true,
                    ],
                )
                ->id,
    ];
}

it(
    'requires authentication to access the cash register',
    function (): void {
        /** @var TestCase $this */

        $this
            ->getJson(
                '/api/v1/admin/cash-register/current'
            )
            ->assertUnauthorized();
    },
);

it(
    'opens a cash session',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/v1/admin/cash-register/open',
                [
                    'opening_amount' =>
                        50,

                    'opening_note' =>
                        'Inicio del turno',
                ],
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'open',
            )
            ->assertJsonPath(
                'data.opening_amount',
                50,
            );

        $this->assertDatabaseHas(
            'cash_sessions',
            [
                'opened_by' =>
                    $admin->id,

                'status' =>
                    CashSessionStatus::Open->value,

                'opening_amount' =>
                    '50.00',
            ],
        );
    },
);

it(
    'does not allow two open cash sessions',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/v1/admin/cash-register/open',
                [
                    'opening_amount' => 20,
                ],
            )
            ->assertCreated();

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/v1/admin/cash-register/open',
                [
                    'opening_amount' => 30,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cash_session',
            ]);
    },
);

it(
    'closes the cash session using delivered cash orders',
    function (): void {
        /** @var TestCase $this */

        CarbonImmutable::setTestNow(
            '2026-08-01 17:00:00'
        );

        $admin = User::factory()
            ->admin()
            ->create();

        $customer = User::factory()
            ->customer()
            ->create();

        $catalog = cashRegisterCatalog();

        $openResponse = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/v1/admin/cash-register/open',
                [
                    'opening_amount' => 50,
                ],
            )
            ->assertCreated();

        $uuid = (string) $openResponse->json(
            'data.uuid'
        );

        $order = Order::query()->create([
            'order_number' =>
                'CH-CASH-SESSION-001',

            'user_id' =>
                $customer->id,

            'ordered_at' =>
                '2026-08-01 17:30:00',

            'subtotal' =>
                '30.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '30.00',

            'delivery_type_id' =>
                $catalog['delivery_type_id'],

            'payment_method_id' =>
                $catalog['cash_method_id'],

            'order_status_id' =>
                $catalog[
                    'delivered_status_id'
                ],
        ]);

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
                '2026-08-01 18:00:00',
        ]);

        CarbonImmutable::setTestNow(
            '2026-08-01 22:00:00'
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/admin/cash-register/{$uuid}/close",
                [
                    'counted_cash' =>
                        79,

                    'closing_note' =>
                        'Falta un dólar',
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'closed',
            )
            ->assertJsonPath(
                'data.expected_cash',
                80,
            )
            ->assertJsonPath(
                'data.counted_cash',
                79,
            )
            ->assertJsonPath(
                'data.difference',
                -1,
            );

        CarbonImmutable::setTestNow();
    },
);

it(
    'returns current session and cash history',
    function (): void {
        /** @var TestCase $this */

        $admin = User::factory()
            ->admin()
            ->create();

        CashSession::query()->create([
            'uuid' =>
                (string) \Illuminate\Support\Str::uuid(),

            'opened_by' =>
                $admin->id,

            'status' =>
                CashSessionStatus::Open,

            'opening_amount' =>
                '25.00',

            'opened_at' =>
                now(),
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/v1/admin/cash-register/current'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'open',
            )
            ->assertJsonPath(
                'data.opening_amount',
                25,
            );

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/v1/admin/cash-register/history'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1,
            )
            ->assertJsonCount(
                1,
                'data',
            );
    },
);
