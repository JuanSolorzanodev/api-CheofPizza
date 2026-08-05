<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Enums\OrderStatusName;
use App\Enums\PaymentReceiptStatus;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\DB;

final class PaymentAnalyticsService
{
    /**
     * Devuelve el resumen financiero del periodo.
     *
     * Cada método utiliza su fecha de reconocimiento real:
     *
     * - Efectivo: fecha en que el pedido cambió a delivered.
     * - Transferencia: fecha de aprobación del comprobante.
     * - PayPal: fecha de captura almacenada en payments.paid_at.
     *
     * @return array<string, mixed>
     */
    public function get(
        AnalyticsDateRangeData $range
    ): array {
        $cash = $this->cashCollections($range);

        $transfer = $this->transferCollections(
            $range
        );

        $paypal = $this->paypalCollections(
            $range
        );

        $pending = $this->pendingCollections(
            $range
        );

        $refunds = $this->refundSummary(
            $range
        );

        $collectedTotal = bcadd(
            bcadd(
                (string) $cash['amount'],
                (string) $transfer['amount'],
                2,
            ),
            (string) $paypal['amount'],
            2,
        );

        return [
            'period' => $range->toArray(),

            'summary' => [
                'collected_total' => (float) $collectedTotal,

                'cash_amount' => $cash['amount'],

                'transfer_amount' => $transfer['amount'],

                'paypal_amount' => $paypal['amount'],

                'cash_orders' => $cash['orders'],

                'transfer_orders' => $transfer['orders'],

                'paypal_payments' => $paypal['payments'],

                'pending_amount' => $pending['amount'],

                'pending_transactions' => $pending['transactions'],

                'refunded_payments' => $refunds['refunded_payments'],

                'partially_refunded_payments' => $refunds[
                        'partially_refunded_payments'
                    ],
            ],

            'methods' => [
                $cash,
                $transfer,
                $paypal,
            ],

            'pending' => $pending,

            'refunds' => $refunds,
        ];
    }

    /**
     * Efectivo cobrado.
     *
     * Un pedido en efectivo se reconoce cuando registra una transición
     * al estado delivered dentro del periodo.
     *
     * Se utiliza order_status_changes.changed_at y no ordered_at.
     *
     * @return array{
     *     method: string,
     *     label: string,
     *     amount: float,
     *     orders: int
     * }
     */
    private function cashCollections(
        AnalyticsDateRangeData $range
    ): array {
        $row = DB::table(
            'order_status_changes'
        )
            ->join(
                'orders',
                'orders.id',
                '=',
                'order_status_changes.order_id',
            )
            ->join(
                'order_statuses',
                'order_statuses.id',
                '=',
                'order_status_changes.to_order_status_id',
            )
            ->join(
                'payment_methods',
                'payment_methods.id',
                '=',
                'orders.payment_method_id',
            )
            ->where(
                'order_statuses.status_name',
                OrderStatusName::Delivered->value,
            )
            ->where(
                'payment_methods.name',
                'cash',
            )
            ->whereBetween(
                'order_status_changes.changed_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                COUNT(DISTINCT orders.id) AS orders_count,
                COALESCE(
                    SUM(orders.total),
                    0
                ) AS collected_amount
                '
            )
            ->first();

        return [
            'method' => 'cash',

            'label' => 'Efectivo',

            'amount' => round(
                (float) (
                    $row->collected_amount
                    ?? 0
                ),
                2,
            ),

            'orders' => (int) (
                $row->orders_count
                ?? 0
            ),
        ];
    }

    /**
     * Transferencias aprobadas.
     *
     * El ingreso se reconoce con payment_receipts.reviewed_at cuando
     * el comprobante fue aprobado.
     *
     * Se evita contar dos veces un mismo pedido aunque existan varios
     * comprobantes aprobados.
     *
     * @return array{
     *     method: string,
     *     label: string,
     *     amount: float,
     *     orders: int
     * }
     */
    private function transferCollections(
        AnalyticsDateRangeData $range
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
                PaymentReceiptStatus::Approved->value,
            )
            ->whereBetween(
                'payment_receipts.reviewed_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                orders.id AS order_id,
                MAX(orders.total) AS order_total
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
                COUNT(order_id) AS orders_count,
                COALESCE(
                    SUM(order_total),
                    0
                ) AS collected_amount
                '
            )
            ->first();

        return [
            'method' => 'transfer',

            'label' => 'Transferencia',

            'amount' => round(
                (float) (
                    $row->collected_amount
                    ?? 0
                ),
                2,
            ),

            'orders' => (int) (
                $row->orders_count
                ?? 0
            ),
        ];
    }

    /**
     * Pagos PayPal completados.
     *
     * Se contabilizan mediante paid_at, que representa la captura real.
     *
     * @return array{
     *     method: string,
     *     label: string,
     *     amount: float,
     *     payments: int
     * }
     */
    private function paypalCollections(
        AnalyticsDateRangeData $range
    ): array {
        $row = DB::table('payments')
            ->where(
                'status',
                PaymentStatus::COMPLETED->value,
            )
            ->whereNotNull(
                'paid_at'
            )
            ->whereBetween(
                'paid_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                COUNT(id) AS payments_count,
                COALESCE(
                    SUM(amount),
                    0
                ) AS collected_amount
                '
            )
            ->first();

        return [
            'method' => 'paypal',

            'label' => 'PayPal',

            'amount' => round(
                (float) (
                    $row->collected_amount
                    ?? 0
                ),
                2,
            ),

            'payments' => (int) (
                $row->payments_count
                ?? 0
            ),
        ];
    }

    /**
     * Operaciones pendientes.
     *
     * Incluye:
     *
     * - transferencias cuyo último comprobante está pending;
     * - pagos PayPal created, pending o approved.
     *
     * @return array<string, mixed>
     */
    private function pendingCollections(
        AnalyticsDateRangeData $range
    ): array {
        $latestReceiptIds = DB::table(
            'payment_receipts'
        )
            ->selectRaw(
                'MAX(id) AS id'
            )
            ->groupBy(
                'order_id'
            );

        $pendingTransfers = DB::table(
            'payment_receipts'
        )
            ->joinSub(
                $latestReceiptIds,
                'latest_receipts',
                static function (
                    $join
                ): void {
                    $join->on(
                        'latest_receipts.id',
                        '=',
                        'payment_receipts.id',
                    );
                },
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
                PaymentReceiptStatus::Pending->value,
            )
            ->whereBetween(
                'payment_receipts.submitted_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                COUNT(orders.id) AS transactions_count,
                COALESCE(
                    SUM(orders.total),
                    0
                ) AS pending_amount
                '
            )
            ->first();

        $pendingPaypal = DB::table(
            'payments'
        )
            ->whereIn(
                'status',
                [
                    PaymentStatus::CREATED->value,
                    PaymentStatus::PENDING->value,
                    PaymentStatus::APPROVED->value,
                ],
            )
            ->whereBetween(
                'created_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                COUNT(id) AS transactions_count,
                COALESCE(
                    SUM(amount),
                    0
                ) AS pending_amount
                '
            )
            ->first();

        $transferAmount = (string) (
            $pendingTransfers->pending_amount
            ?? '0.00'
        );

        $paypalAmount = (string) (
            $pendingPaypal->pending_amount
            ?? '0.00'
        );

        $totalAmount = bcadd(
            $transferAmount,
            $paypalAmount,
            2,
        );

        $transferCount = (int) (
            $pendingTransfers->transactions_count
            ?? 0
        );

        $paypalCount = (int) (
            $pendingPaypal->transactions_count
            ?? 0
        );

        return [
            'amount' => (float) $totalAmount,

            'transactions' => $transferCount
                + $paypalCount,

            'transfer' => [
                'amount' => (float) $transferAmount,

                'transactions' => $transferCount,
            ],

            'paypal' => [
                'amount' => (float) $paypalAmount,

                'transactions' => $paypalCount,
            ],
        ];
    }

    /**
     * Estado de reembolsos.
     *
     * La tabla actual no almacena refunded_amount, por lo que no se
     * presenta un monto inventado. Solo se reportan cantidades.
     *
     * @return array{
     *     refunded_payments: int,
     *     partially_refunded_payments: int,
     *     refundable_amount_available: bool
     * }
     */
    private function refundSummary(
        AnalyticsDateRangeData $range
    ): array {
        $rows = DB::table('payments')
            ->whereIn(
                'status',
                [
                    PaymentStatus::REFUNDED->value,
                    PaymentStatus::PARTIALLY_REFUNDED->value,
                ],
            )
            ->whereBetween(
                'refunded_at',
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                status,
                COUNT(id) AS payments_count
                '
            )
            ->groupBy(
                'status'
            )
            ->pluck(
                'payments_count',
                'status',
            );

        return [
            'refunded_payments' => (int) (
                $rows[
                    PaymentStatus::REFUNDED->value
                ]
                ?? 0
            ),

            'partially_refunded_payments' => (int) (
                $rows[
                    PaymentStatus::PARTIALLY_REFUNDED->value
                ]
                ?? 0
            ),

            'refundable_amount_available' => false,
        ];
    }
}
