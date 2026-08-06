<?php

declare(strict_types=1);

namespace App\Services\Admin\CashRegister;

use App\Enums\OrderStatusName;
use App\Models\CashSession;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CashOrderCollectionQueryService
{
    public function deliveredOrdersBetween(
        mixed $openedAt,
        mixed $closedAt,
    ): Builder {
        return DB::table(
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
                    $openedAt,
                    $closedAt,
                ],
            )
            ->selectRaw(
                '
                order_status_changes.order_id,
                MIN(order_status_changes.changed_at) AS delivered_at
                ',
            )
            ->groupBy(
                'order_status_changes.order_id',
            );
    }

    /**
     * @return array{
     *     amount: string,
     *     transactions: int
     * }
     */
    public function summary(
        CashSession $session,
        mixed $endAt,
    ): array {
        $deliveredOrders = $this->deliveredOrdersBetween(
            openedAt: $session->opened_at,
            closedAt: $endAt,
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
                COUNT(DISTINCT orders.id) AS transactions_count,
                COALESCE(SUM(orders.total), 0) AS amount
                ',
            )
            ->first();

        return [
            'amount' => (string) ($row->amount ?? '0'),
            'transactions' => (int) ($row->transactions_count ?? 0),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function details(
        CashSession $session,
        mixed $endAt,
    ): Collection {
        $deliveredOrders = $this->deliveredOrdersBetween(
            openedAt: $session->opened_at,
            closedAt: $endAt,
        );

        return DB::table('orders')
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
            ->orderByDesc(
                'delivered_orders.delivered_at',
            )
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.total',
                'orders.ordered_at',
                'delivered_orders.delivered_at',
                'users.id as customer_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
            ])
            ->get();
    }
}
