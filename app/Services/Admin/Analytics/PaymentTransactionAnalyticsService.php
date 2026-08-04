<?php

declare(strict_types=1);

namespace App\Services\Admin\Analytics;

use App\Data\Admin\Analytics\AnalyticsDateRangeData;
use App\Enums\OrderStatusName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PaymentTransactionAnalyticsService
{
    /**
     * Estados que representan dinero efectivamente recaudado.
     *
     * @var list<string>
     */
    private const SUCCESSFUL_STATUSES = [
        'collected',
        'approved',
        'completed',
    ];

    /**
     * Estados que representan operaciones todavía no resueltas.
     *
     * @var list<string>
     */
    private const PENDING_STATUSES = [
        'pending',
        'created',
    ];

    /**
     * Estados que no representan recaudación efectiva.
     *
     * @var list<string>
     */
    private const UNSUCCESSFUL_STATUSES = [
        'rejected',
        'denied',
        'failed',
        'cancelled',
        'refunded',
        'partially_refunded',
    ];

    /**
     * Devuelve una lista unificada y paginada.
     *
     * @param array{
     *     method?: string|null,
     *     status?: string|null,
     *     search?: string|null,
     *     page: int,
     *     per_page: int
     * } $filters
     */
    public function paginate(
        AnalyticsDateRangeData $range,
        array $filters,
    ): LengthAwarePaginator {
        $query = $this->filteredQuery(
            range: $range,
            filters: $filters,
        );

        return $query
            ->orderByDesc(
                'payment_transactions.effective_at',
            )
            ->orderByDesc(
                'payment_transactions.source_id',
            )
            ->paginate(
                perPage: (int) $filters['per_page'],
                columns: ['*'],
                pageName: 'page',
                page: (int) $filters['page'],
            )
            ->through(
                static fn (object $row): array =>
                    self::mapTransaction($row),
            );
    }

    /**
     * Devuelve indicadores globales aplicando los mismos filtros,
     * pero sin limitar los resultados por paginación.
     *
     * Los importes por método representan únicamente operaciones
     * efectivamente recaudadas.
     *
     * @param array{
     *     method?: string|null,
     *     status?: string|null,
     *     search?: string|null,
     *     page: int,
     *     per_page: int
     * } $filters
     *
     * @return array<string, mixed>
     */
    public function summary(
        AnalyticsDateRangeData $range,
        array $filters,
    ): array {
        $query = $this->filteredQuery(
            range: $range,
            filters: $filters,
        );

        $row = DB::query()
            ->fromSub(
                $query,
                'filtered_transactions',
            )
            ->selectRaw(
                '
                COUNT(*) AS volume_transactions,

                COALESCE(
                    SUM(amount),
                    0
                ) AS volume_amount,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status IN (
                                "collected",
                                "approved",
                                "completed"
                            )
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS collected_amount,

                SUM(
                    CASE
                        WHEN status IN (
                            "collected",
                            "approved",
                            "completed"
                        )
                        THEN 1
                        ELSE 0
                    END
                ) AS collected_transactions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN method = "cash"
                            AND status = "collected"
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS cash_amount,

                SUM(
                    CASE
                        WHEN method = "cash"
                        AND status = "collected"
                        THEN 1
                        ELSE 0
                    END
                ) AS cash_transactions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN method = "transfer"
                            AND status = "approved"
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS transfer_amount,

                SUM(
                    CASE
                        WHEN method = "transfer"
                        AND status = "approved"
                        THEN 1
                        ELSE 0
                    END
                ) AS transfer_transactions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN method = "paypal"
                            AND status = "completed"
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS paypal_amount,

                SUM(
                    CASE
                        WHEN method = "paypal"
                        AND status = "completed"
                        THEN 1
                        ELSE 0
                    END
                ) AS paypal_transactions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status IN (
                                "pending",
                                "created"
                            )
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS pending_amount,

                SUM(
                    CASE
                        WHEN status IN (
                            "pending",
                            "created"
                        )
                        THEN 1
                        ELSE 0
                    END
                ) AS pending_transactions,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status IN (
                                "rejected",
                                "denied",
                                "failed",
                                "cancelled",
                                "refunded",
                                "partially_refunded"
                            )
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS unsuccessful_amount,

                SUM(
                    CASE
                        WHEN status IN (
                            "rejected",
                            "denied",
                            "failed",
                            "cancelled",
                            "refunded",
                            "partially_refunded"
                        )
                        THEN 1
                        ELSE 0
                    END
                ) AS unsuccessful_transactions
                '
            )
            ->first();

        return [
            'volume' => [
                'amount' =>
                    $this->money(
                        $row->volume_amount ?? 0,
                    ),

                'transactions' =>
                    (int) (
                        $row->volume_transactions
                        ?? 0
                    ),
            ],

            'collected' => [
                'amount' =>
                    $this->money(
                        $row->collected_amount ?? 0,
                    ),

                'transactions' =>
                    (int) (
                        $row->collected_transactions
                        ?? 0
                    ),
            ],

            'methods' => [
                'cash' => [
                    'amount' =>
                        $this->money(
                            $row->cash_amount ?? 0,
                        ),

                    'transactions' =>
                        (int) (
                            $row->cash_transactions
                            ?? 0
                        ),
                ],

                'transfer' => [
                    'amount' =>
                        $this->money(
                            $row->transfer_amount ?? 0,
                        ),

                    'transactions' =>
                        (int) (
                            $row->transfer_transactions
                            ?? 0
                        ),
                ],

                'paypal' => [
                    'amount' =>
                        $this->money(
                            $row->paypal_amount ?? 0,
                        ),

                    'transactions' =>
                        (int) (
                            $row->paypal_transactions
                            ?? 0
                        ),
                ],
            ],

            'pending' => [
                'amount' =>
                    $this->money(
                        $row->pending_amount ?? 0,
                    ),

                'transactions' =>
                    (int) (
                        $row->pending_transactions
                        ?? 0
                    ),
            ],

            'unsuccessful' => [
                'amount' =>
                    $this->money(
                        $row->unsuccessful_amount ?? 0,
                    ),

                'transactions' =>
                    (int) (
                        $row->unsuccessful_transactions
                        ?? 0
                    ),
            ],
        ];
    }

    /**
     * Construye la consulta unificada y aplica los filtros comunes.
     *
     * @param array{
     *     method?: string|null,
     *     status?: string|null,
     *     search?: string|null,
     *     page: int,
     *     per_page: int
     * } $filters
     */
    private function filteredQuery(
        AnalyticsDateRangeData $range,
        array $filters,
    ): Builder {
        $query = DB::query()
            ->fromSub(
                $this->unifiedTransactions(
                    $range,
                ),
                'payment_transactions',
            );

        if (filled($filters['method'] ?? null)) {
            $query->where(
                'payment_transactions.method',
                (string) $filters['method'],
            );
        }

        if (filled($filters['status'] ?? null)) {
            $query->where(
                'payment_transactions.status',
                (string) $filters['status'],
            );
        }

        if (filled($filters['search'] ?? null)) {
            $search = '%'
                . $this->escapeLike(
                    (string) $filters['search'],
                )
                . '%';

            $query->where(
                static function (
                    Builder $builder
                ) use ($search): void {
                    $builder
                        ->where(
                            'payment_transactions.order_number',
                            'like',
                            $search,
                        )
                        ->orWhere(
                            'payment_transactions.customer_name',
                            'like',
                            $search,
                        )
                        ->orWhere(
                            'payment_transactions.customer_email',
                            'like',
                            $search,
                        )
                        ->orWhere(
                            'payment_transactions.customer_phone',
                            'like',
                            $search,
                        )
                        ->orWhere(
                            'payment_transactions.reference',
                            'like',
                            $search,
                        );
                },
            );
        }

        return $query;
    }

    private function unifiedTransactions(
        AnalyticsDateRangeData $range,
    ): Builder {
        return $this
            ->cashTransactions($range)
            ->unionAll(
                $this->transferTransactions(
                    $range,
                ),
            )
            ->unionAll(
                $this->paypalTransactions(
                    $range,
                ),
            );
    }

    /**
     * Un pedido en efectivo se reconoce una sola vez:
     * en su primera transición a delivered del periodo.
     */
    private function cashTransactions(
        AnalyticsDateRangeData $range,
    ): Builder {
        $deliveredChanges = DB::table(
            'order_status_changes',
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
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                order_status_changes.order_id,
                MIN(
                    order_status_changes.changed_at
                ) AS effective_at
                ',
            )
            ->groupBy(
                'order_status_changes.order_id',
            );

        return DB::table('orders')
            ->joinSub(
                $deliveredChanges,
                'delivered_changes',
                static function ($join): void {
                    $join->on(
                        'delivered_changes.order_id',
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
            ->join(
                'users',
                'users.id',
                '=',
                'orders.user_id',
            )
            ->where(
                'payment_methods.name',
                'cash',
            )
            ->selectRaw(
                '
                CONCAT(
                    "cash:",
                    orders.id
                ) AS transaction_key,

                "order" AS source,
                orders.id AS source_id,
                "cash" AS method,
                "collected" AS status,
                orders.total AS amount,
                "USD" AS currency,
                delivered_changes.effective_at AS effective_at,
                orders.id AS order_id,
                orders.order_number AS order_number,
                users.id AS customer_id,

                TRIM(
                    CONCAT(
                        users.first_name,
                        " ",
                        users.last_name
                    )
                ) AS customer_name,

                users.email AS customer_email,
                users.phone AS customer_phone,
                orders.order_number AS reference,
                NULL AS receipt_uuid,
                NULL AS reviewed_by,
                NULL AS failure_code
                ',
            );
    }

    /**
     * Solo se muestra el último comprobante de cada pedido.
     */
    private function transferTransactions(
        AnalyticsDateRangeData $range,
    ): Builder {
        $latestReceiptIds = DB::table(
            'payment_receipts',
        )
            ->selectRaw(
                'MAX(id) AS id',
            )
            ->groupBy(
                'order_id',
            );

        return DB::table(
            'payment_receipts',
        )
            ->joinSub(
                $latestReceiptIds,
                'latest_receipts',
                static function ($join): void {
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
            ->join(
                'users',
                'users.id',
                '=',
                'payment_receipts.user_id',
            )
            ->leftJoin(
                'users AS reviewers',
                'reviewers.id',
                '=',
                'payment_receipts.reviewed_by',
            )
            ->where(
                'payment_methods.name',
                'transfer',
            )
            ->whereBetween(
                DB::raw(
                    'COALESCE(
                        payment_receipts.reviewed_at,
                        payment_receipts.submitted_at
                    )',
                ),
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                CONCAT(
                    "transfer:",
                    payment_receipts.id
                ) AS transaction_key,

                "payment_receipt" AS source,
                payment_receipts.id AS source_id,
                "transfer" AS method,
                payment_receipts.status AS status,
                orders.total AS amount,
                "USD" AS currency,

                COALESCE(
                    payment_receipts.reviewed_at,
                    payment_receipts.submitted_at
                ) AS effective_at,

                orders.id AS order_id,
                orders.order_number AS order_number,
                users.id AS customer_id,

                TRIM(
                    CONCAT(
                        users.first_name,
                        " ",
                        users.last_name
                    )
                ) AS customer_name,

                users.email AS customer_email,
                users.phone AS customer_phone,
                payment_receipts.uuid AS reference,
                payment_receipts.uuid AS receipt_uuid,

                NULLIF(
                    TRIM(
                        CONCAT(
                            reviewers.first_name,
                            " ",
                            reviewers.last_name
                        )
                    ),
                    ""
                ) AS reviewed_by,

                NULL AS failure_code
                ',
            );
    }

    private function paypalTransactions(
        AnalyticsDateRangeData $range,
    ): Builder {
        $effectiveAt = '
            CASE payments.status
                WHEN "refunded"
                    THEN COALESCE(
                        payments.refunded_at,
                        payments.updated_at
                    )

                WHEN "partially_refunded"
                    THEN COALESCE(
                        payments.refunded_at,
                        payments.updated_at
                    )

                WHEN "completed"
                    THEN COALESCE(
                        payments.paid_at,
                        payments.updated_at
                    )

                WHEN "approved"
                    THEN COALESCE(
                        payments.approved_at,
                        payments.updated_at
                    )

                WHEN "failed"
                    THEN COALESCE(
                        payments.failed_at,
                        payments.updated_at
                    )

                WHEN "denied"
                    THEN COALESCE(
                        payments.failed_at,
                        payments.updated_at
                    )

                WHEN "cancelled"
                    THEN COALESCE(
                        payments.cancelled_at,
                        payments.updated_at
                    )

                ELSE payments.created_at
            END
        ';

        return DB::table('payments')
            ->join(
                'users',
                'users.id',
                '=',
                'payments.user_id',
            )
            ->leftJoin(
                'orders',
                'orders.id',
                '=',
                'payments.order_id',
            )
            ->whereBetween(
                DB::raw($effectiveAt),
                [
                    $range->from,
                    $range->to,
                ],
            )
            ->selectRaw(
                '
                CONCAT(
                    "paypal:",
                    payments.id
                ) AS transaction_key,

                "payment" AS source,
                payments.id AS source_id,
                "paypal" AS method,
                payments.status AS status,
                payments.amount AS amount,
                payments.currency AS currency,
                '
                . $effectiveAt
                . ' AS effective_at,

                orders.id AS order_id,
                orders.order_number AS order_number,
                users.id AS customer_id,

                TRIM(
                    CONCAT(
                        users.first_name,
                        " ",
                        users.last_name
                    )
                ) AS customer_name,

                users.email AS customer_email,
                users.phone AS customer_phone,

                COALESCE(
                    payments.provider_capture_id,
                    payments.provider_order_id,
                    payments.uuid
                ) AS reference,

                NULL AS receipt_uuid,
                NULL AS reviewed_by,
                payments.failure_code AS failure_code
                ',
            );
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapTransaction(
        object $row,
    ): array {
        return [
            'transaction_key' =>
                (string) $row->transaction_key,

            'source' =>
                (string) $row->source,

            'source_id' =>
                (int) $row->source_id,

            'method' =>
                (string) $row->method,

            'status' =>
                (string) $row->status,

            'amount' =>
                round(
                    (float) $row->amount,
                    2,
                ),

            'currency' =>
                (string) $row->currency,

            'effective_at' =>
                (string) $row->effective_at,

            'order_id' =>
                $row->order_id !== null
                    ? (int) $row->order_id
                    : null,

            'order_number' =>
                $row->order_number !== null
                    ? (string) $row->order_number
                    : null,

            'customer' => [
                'id' =>
                    $row->customer_id !== null
                        ? (int) $row->customer_id
                        : null,

                'name' =>
                    trim(
                        (string) $row->customer_name,
                    ),

                'email' =>
                    $row->customer_email !== null
                        ? (string) $row->customer_email
                        : null,

                'phone' =>
                    $row->customer_phone !== null
                        ? (string) $row->customer_phone
                        : null,
            ],

            'reference' =>
                $row->reference !== null
                    ? (string) $row->reference
                    : null,

            'receipt_uuid' =>
                $row->receipt_uuid !== null
                    ? (string) $row->receipt_uuid
                    : null,

            'reviewed_by' =>
                $row->reviewed_by !== null
                    ? trim(
                        (string) $row->reviewed_by,
                    )
                    : null,

            'failure_code' =>
                $row->failure_code !== null
                    ? (string) $row->failure_code
                    : null,
        ];
    }

    private function money(
        int|float|string $value,
    ): float {
        return round(
            (float) $value,
            2,
        );
    }

    private function escapeLike(
        string $value,
    ): string {
        return addcslashes(
            $value,
            '\\%_',
        );
    }
}
