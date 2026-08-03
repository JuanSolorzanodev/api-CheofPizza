<?php

declare(strict_types=1);

namespace App\Services\Admin\CashRegister;

use App\Enums\CashMovementType;
use App\Enums\OrderStatusName;
use App\Models\CashSession;
use Illuminate\Support\Facades\DB;

final class CashSessionSummaryService
{
    /**
     * Devuelve el estado financiero actual de una sesión de caja.
     *
     * El efectivo esperado se calcula en tiempo real:
     *
     * apertura + ventas en efectivo + ingresos - egresos.
     *
     * @return array<string, mixed>
     */
    public function get(
        CashSession $session
    ): array {
        $closedAt = $session->closed_at ?? now();

        $cashSales = $this->cashSales(
            session: $session,
            closedAt: $closedAt,
        );

        $movements = $this->movementTotals(
            $session
        );

        $expectedCash = bcsub(
            bcadd(
                bcadd(
                    (string) $session->opening_amount,
                    $cashSales['amount'],
                    2,
                ),
                $movements['income_amount'],
                2,
            ),
            $movements['expense_amount'],
            2,
        );

        return [
            'session' => [
                'uuid' =>
                $session->uuid,

                'status' =>
                $session->status->value,

                'opened_at' =>
                $session->opened_at?->toISOString(),

                'closed_at' =>
                $session->closed_at?->toISOString(),

                'opened_by' => [
                    'id' =>
                    $session->openedBy?->id,

                    'name' =>
                    $session->openedBy?->full_name,
                ],

                'closed_by' =>
                $session->closedBy !== null
                    ? [
                        'id' =>
                        $session->closedBy->id,

                        'name' =>
                        $session->closedBy->full_name,
                    ]
                    : null,
            ],

            'amounts' => [
                'opening_amount' =>
                (float) $session->opening_amount,

                'cash_sales' =>
                (float) $cashSales['amount'],

                'manual_income' =>
                (float) $movements['income_amount'],

                'manual_expense' =>
                (float) $movements['expense_amount'],

                'expected_cash' =>
                (float) $expectedCash,

                'counted_cash' =>
                $session->counted_cash !== null
                    ? (float) $session->counted_cash
                    : null,

                'difference' =>
                $session->difference !== null
                    ? (float) $session->difference
                    : null,
            ],

            'activity' => [
                'cash_orders' =>
                $cashSales['orders'],

                'income_movements' =>
                $movements['income_count'],

                'expense_movements' =>
                $movements['expense_count'],

                'movements_total' =>
                $movements['income_count']
                    + $movements['expense_count'],
            ],
        ];
    }

    /**
     * @return array{
     *     amount: string,
     *     orders: int
     * }
     */
    private function cashSales(
        CashSession $session,
        mixed $closedAt,
    ): array {
        $deliveredOrders = DB::table(
            'order_status_changes'
        )
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'order_status_changes.to_order_status_id',
            )
            ->where(
                'order_statuses.status_name',
                OrderStatusName::Delivered->value,
            )
            ->whereBetween(
                'order_status_changes.changed_at',
                [
                    $session->opened_at,
                    $closedAt,
                ],
            )
            ->selectRaw(
                '
                order_status_changes.order_id,
                MIN(order_status_changes.changed_at) AS delivered_at
                '
            )
            ->groupBy(
                'order_status_changes.order_id',
            );

        $row = DB::table('orders')
            ->joinSub(
                $deliveredOrders,
                'delivered_orders',
                static function ($join): void {
                    $join->on(
                        'delivered_orders.order_id',
                        '=',
                        'orders.id',
                    );
                },
            )
            ->join(
                'payment_methods',
                'payment_methods.id',
                '=',
                'orders.payment_method_id',
            )
            ->where(
                'payment_methods.name',
                'cash',
            )
            ->selectRaw(
                '
                COUNT(DISTINCT orders.id) AS orders_count,
                COALESCE(SUM(orders.total), 0) AS amount
                '
            )
            ->first();

        return [
            'amount' =>
            $this->money(
                $row->amount ?? 0
            ),

            'orders' =>
            (int) (
                $row->orders_count ?? 0
            ),
        ];
    }

    /**
     * @return array{
     *     income_amount: string,
     *     expense_amount: string,
     *     income_count: int,
     *     expense_count: int
     * }
     */
    private function movementTotals(
        CashSession $session
    ): array {
        $rows = $session
            ->movements()
            ->selectRaw(
                '
        type,
        COUNT(id) AS movements_count,
        COALESCE(SUM(amount), 0) AS amount
        '
            )
            ->groupBy('type')
            ->get()
            ->keyBy(
                static function (object $row): string {
                    return $row->type instanceof CashMovementType
                        ? $row->type->value
                        : (string) $row->type;
                },
            );

        $income = $rows->get(
            CashMovementType::Income->value
        );

        $expense = $rows->get(
            CashMovementType::Expense->value
        );

        return [
            'income_amount' =>
            $this->money(
                $income->amount ?? 0
            ),

            'expense_amount' =>
            $this->money(
                $expense->amount ?? 0
            ),

            'income_count' =>
            (int) (
                $income->movements_count ?? 0
            ),

            'expense_count' =>
            (int) (
                $expense->movements_count ?? 0
            ),
        ];
    }

    private function money(
        int|float|string $value
    ): string {
        return number_format(
            (float) $value,
            2,
            '.',
            '',
        );
    }
}
