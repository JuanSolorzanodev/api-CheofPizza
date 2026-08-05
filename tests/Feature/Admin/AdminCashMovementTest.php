<?php

declare(strict_types=1);

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

function openCashSessionForMovements(
    User $admin,
    string $openingAmount = '50.00',
): CashSession {
    return CashSession::query()->create([
        'uuid' => (string) Str::uuid(),

        'opened_by' => $admin->id,

        'status' => CashSessionStatus::Open,

        'opening_amount' => $openingAmount,

        'opened_at' => now(),
    ]);
}

it(
    'registers income and expense movements in an open cash session',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $session = openCashSessionForMovements(
            $admin
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/admin/cash-register/{$session->uuid}/movements",
                [
                    'type' => 'income',

                    'amount' => 10,

                    'reason' => 'Cambio adicional',
                ],
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.type',
                'income',
            )
            ->assertJsonPath(
                'data.amount',
                10,
            );

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/admin/cash-register/{$session->uuid}/movements",
                [
                    'type' => 'expense',

                    'amount' => 4,

                    'reason' => 'Compra de bolsas',
                ],
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.type',
                'expense',
            )
            ->assertJsonPath(
                'data.amount',
                4,
            );

        $this->assertDatabaseHas(
            'cash_movements',
            [
                'cash_session_id' => $session->id,

                'created_by' => $admin->id,

                'type' => CashMovementType::Income->value,

                'amount' => '10.00',
            ],
        );
    },
);

it(
    'rejects movements when the cash session is closed',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $session = CashSession::query()->create([
            'uuid' => (string) Str::uuid(),

            'opened_by' => $admin->id,

            'closed_by' => $admin->id,

            'status' => CashSessionStatus::Closed,

            'opening_amount' => '20.00',

            'expected_cash' => '20.00',

            'counted_cash' => '20.00',

            'difference' => '0.00',

            'opened_at' => now()->subHour(),

            'closed_at' => now(),
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/admin/cash-register/{$session->uuid}/movements",
                [
                    'type' => 'income',

                    'amount' => 5,

                    'reason' => 'Movimiento inválido',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cash_session',
            ]);
    },
);

it(
    'lists movements for a cash session',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $session = openCashSessionForMovements(
            $admin
        );

        CashMovement::query()->create([
            'uuid' => (string) Str::uuid(),

            'cash_session_id' => $session->id,

            'created_by' => $admin->id,

            'type' => CashMovementType::Income,

            'amount' => '8.00',

            'reason' => 'Ingreso de prueba',

            'occurred_at' => now(),
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/v1/admin/cash-register/{$session->uuid}/movements"
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
                'data.0.reason',
                'Ingreso de prueba',
            );
    },
);

it(
    'includes manual movements in the cash closing calculation',
    function (): void {
        /** @var TestCase $this */
        CarbonImmutable::setTestNow(
            '2026-08-03 17:00:00'
        );

        $admin = User::factory()
            ->admin()
            ->create();

        $session = openCashSessionForMovements(
            admin: $admin,
            openingAmount: '50.00',
        );

        CashMovement::query()->create([
            'uuid' => (string) Str::uuid(),

            'cash_session_id' => $session->id,

            'created_by' => $admin->id,

            'type' => CashMovementType::Income,

            'amount' => '10.00',

            'reason' => 'Ingreso adicional',

            'occurred_at' => now(),
        ]);

        CashMovement::query()->create([
            'uuid' => (string) Str::uuid(),

            'cash_session_id' => $session->id,

            'created_by' => $admin->id,

            'type' => CashMovementType::Expense,

            'amount' => '4.00',

            'reason' => 'Egreso operativo',

            'occurred_at' => now(),
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/admin/cash-register/{$session->uuid}/close",
                [
                    'counted_cash' => 56,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.expected_cash',
                56,
            )
            ->assertJsonPath(
                'data.counted_cash',
                56,
            )
            ->assertJsonPath(
                'data.difference',
                0,
            );

        CarbonImmutable::setTestNow();
    },
);

it(
    'validates movement data',
    function (): void {
        /** @var TestCase $this */
        $admin = User::factory()
            ->admin()
            ->create();

        $session = openCashSessionForMovements(
            $admin
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/admin/cash-register/{$session->uuid}/movements",
                [
                    'type' => 'invalid',

                    'amount' => 0,

                    'reason' => '',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'amount',
                'reason',
            ]);
    },
);
