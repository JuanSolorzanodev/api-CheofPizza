<?php

declare(strict_types=1);

namespace App\Services\Admin\CashRegister;

use App\Enums\CashMovementType;
use App\Enums\PaymentReceiptStatus;
use App\Enums\PaymentStatus;
use App\Models\CashSession;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

final class CashSessionSummaryService
{
    public function __construct(
        private readonly CashOrderCollectionQueryService $cashOrderQueryService,
    ) {}

    /**
     * Devuelve el estado financiero actual de una sesión de caja.
     *
     * Caja física:
     *
     * apertura + ventas en efectivo + ingresos - egresos.
     *
     * Recaudación total:
     *
     * efectivo + transferencias aprobadas + PayPal completado.
     *
     * @return array<string, mixed>
     */
    public function get(
        CashSession $session
    ): array {
        $endAt =
            $session->closed_at
            ?? now();

        $cash = $this->cashCollections(
            session: $session,
            endAt: $endAt,
        );

        $transfer =
            $this->transferCollections(
                session: $session,
                endAt: $endAt,
            );

        $paypal =
            $this->paypalCollections(
                session: $session,
                endAt: $endAt,
            );

        $movements =
            $this->movementTotals(
                $session
            );

        $expectedCash = bcsub(
            bcadd(
                bcadd(
                    (string) $session->opening_amount,
                    $cash['amount'],
                    2,
                ),
                $movements['income_amount'],
                2,
            ),
            $movements['expense_amount'],
            2,
        );

        $totalCollected = bcadd(
            bcadd(
                $cash['amount'],
                $transfer['amount'],
                2,
            ),
            $paypal['amount'],
            2,
        );

        return [
            'session' => [
                'uuid' => $session->uuid,

                'status' => $session->status->value,

                'opened_at' => $session
                    ->opened_at
                    ?->toISOString(),

                'closed_at' => $session
                    ->closed_at
                    ?->toISOString(),

                'opened_by' => [
                    'id' => $session
                        ->openedBy
                        ?->id,

                    'name' => $session
                        ->openedBy
                        ?->full_name,
                ],

                'closed_by' => $session->closedBy !== null
                    ? [
                        'id' => $session
                            ->closedBy
                            ->id,

                        'name' => $session
                            ->closedBy
                            ->full_name,
                    ]
                    : null,
            ],

            /*
            |--------------------------------------------------------------------------
            | Caja física
            |--------------------------------------------------------------------------
            */

            'amounts' => [
                'opening_amount' => (float) $session
                    ->opening_amount,

                'cash_sales' => (float) $cash['amount'],

                'manual_income' => (float) $movements['income_amount'],

                'manual_expense' => (float) $movements['expense_amount'],

                'expected_cash' => (float) $expectedCash,

                'counted_cash' => $session->counted_cash !== null
                    ? (float) $session
                        ->counted_cash
                    : null,

                'difference' => $session->difference !== null
                    ? (float) $session
                        ->difference
                    : null,
            ],

            /*
            |--------------------------------------------------------------------------
            | Recaudación total
            |--------------------------------------------------------------------------
            */

            'collections' => [
                'total_collected' => (float) $totalCollected,

                'cash' => [
                    'amount' => (float) $cash['amount'],

                    'transactions' => $cash['transactions'],
                ],

                'transfer' => [
                    'amount' => (float) $transfer['amount'],

                    'transactions' => $transfer['transactions'],
                ],

                'paypal' => [
                    'amount' => (float) $paypal['amount'],

                    'transactions' => $paypal['transactions'],
                ],
            ],

            'activity' => [
                'cash_orders' => $cash['transactions'],

                'transfer_orders' => $transfer['transactions'],

                'paypal_payments' => $paypal['transactions'],

                'collected_transactions' => $cash['transactions']
                    + $transfer['transactions']
                    + $paypal['transactions'],

                'income_movements' => $movements['income_count'],

                'expense_movements' => $movements['expense_count'],

                'movements_total' => $movements['income_count']
                    + $movements['expense_count'],
            ],
        ];
    }

    /**
     * Reconoce pedidos pagados en efectivo cuando alcanzan delivered.
     *
     * @return array{
     *     amount: string,
     *     transactions: int
     * }
     */
    private function cashCollections(
        CashSession $session,
        mixed $endAt,
    ): array {
        $summary = $this->cashOrderQueryService->summary(
            session: $session,
            endAt: $endAt,
        );

        return [
            'amount' => $this->money(
                $summary['amount'],
            ),

            'transactions' => $summary['transactions'],
        ];
    }

    /**
     * Reconoce transferencias cuando el comprobante fue aprobado
     * durante la sesión.
     *
     * Un pedido se cuenta una sola vez aunque tuviera más de un
     * comprobante aprobado.
     *
     * @return array{
     *     amount: string,
     *     transactions: int
     * }
     */
    private function transferCollections(
        CashSession $session,
        mixed $endAt,
    ): array {
        $approvedOrders = DB::table(
            'payment_receipts'
        )
            ->join(
                'orders',
                'orders.id',
                '=',
                'payment_receipts.order_id',
            )
            ->join(
                'payment_methods',
                'payment_methods.id',
                '=',
                'orders.payment_method_id',
            )
            ->where(
                'payment_methods.name',
                'transfer',
            )
            ->where(
                'payment_receipts.status',
                PaymentReceiptStatus::Approved
                    ->value,
            )
            ->whereNotNull(
                'payment_receipts.reviewed_at'
            )
            ->whereBetween(
                'payment_receipts.reviewed_at',
                [
                    $session->opened_at,
                    $endAt,
                ],
            )
            ->selectRaw(
                '
                orders.id AS order_id,
                MAX(
                    orders.total
                ) AS order_total
                '
            )
            ->groupBy(
                'orders.id'
            );

        $row = DB::query()
            ->fromSub(
                $approvedOrders,
                'approved_transfer_orders',
            )
            ->selectRaw(
                '
                COUNT(
                    order_id
                ) AS transactions_count,

                COALESCE(
                    SUM(order_total),
                    0
                ) AS amount
                '
            )
            ->first();

        return [
            'amount' => $this->money(
                $row->amount ?? 0
            ),

            'transactions' => (int) (
                $row
                    ->transactions_count
                ?? 0
            ),
        ];
    }

    /**
     * Reconoce PayPal cuando el pago fue capturado y paid_at
     * pertenece a la sesión.
     *
     * @return array{
     *     amount: string,
     *     transactions: int
     * }
     */
    private function paypalCollections(
        CashSession $session,
        mixed $endAt,
    ): array {
        $row = DB::table('payments')
            ->where(
                'status',
                PaymentStatus::COMPLETED
                    ->value,
            )
            ->whereNotNull('paid_at')
            ->whereBetween(
                'paid_at',
                [
                    $session->opened_at,
                    $endAt,
                ],
            )
            ->selectRaw(
                '
                COUNT(
                    id
                ) AS transactions_count,

                COALESCE(
                    SUM(amount),
                    0
                ) AS amount
                '
            )
            ->first();

        return [
            'amount' => $this->money(
                $row->amount ?? 0
            ),

            'transactions' => (int) (
                $row
                    ->transactions_count
                ?? 0
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
                COALESCE(
                    SUM(amount),
                    0
                ) AS amount
                '
            )
            ->groupBy('type')
            ->get()
            ->keyBy(
                static function (
                    object $row
                ): string {
                    return $row->type
                        instanceof CashMovementType
                        ? $row->type->value
                        : (string) $row->type;
                },
            );

        $income = $rows->get(
            CashMovementType::Income
                ->value
        );

        $expense = $rows->get(
            CashMovementType::Expense
                ->value
        );

        return [
            'income_amount' => $this->money(
                $income->amount ?? 0
            ),

            'expense_amount' => $this->money(
                $expense->amount ?? 0
            ),

            'income_count' => (int) (
                $income
                    ->movements_count
                ?? 0
            ),

            'expense_count' => (int) (
                $expense
                    ->movements_count
                ?? 0
            ),
        ];
    }

    private function money(
        int|float|string $value,
    ): string {
        return BigDecimal::of(
            (string) $value,
        )
            ->toScale(
                2,
                RoundingMode::HALF_UP,
            )
            ->__toString();
    }
}
