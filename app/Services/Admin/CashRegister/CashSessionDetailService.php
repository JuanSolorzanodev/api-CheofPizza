<?php

declare(strict_types=1);

namespace App\Services\Admin\CashRegister;

use App\Enums\OrderStatusName;
use App\Http\Resources\Api\V1\Admin\CashRegister\CashMovementResource;
use App\Http\Resources\Api\V1\Admin\CashRegister\CashSessionResource;
use App\Models\CashSession;
use Illuminate\Support\Facades\DB;

final class CashSessionDetailService
{
    public function __construct(
        private readonly CashSessionSummaryService $summaryService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(
        CashSession $session
    ): array {
        $session->load([
            'openedBy',
            'closedBy',
            'movements.createdBy',
        ]);

        return [
            'session' =>
                (new CashSessionResource($session))
                    ->toArray(request()),

            'summary' =>
                $this->summaryService->get($session),

            'cash_orders' =>
                $this->cashOrders($session),

            'movements' =>
                CashMovementResource::collection(
                    $session->movements
                        ->sortByDesc('occurred_at')
                        ->values()
                )->resolve(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cashOrders(
        CashSession $session
    ): array {
        $end = $session->closed_at ?? now();

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
                    $end,
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
                'delivered_orders.delivered_at'
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
            ->get()
            ->map(
                static function (object $row): array {
                    return [
                        'id' =>
                            (int) $row->id,

                        'order_number' =>
                            (string) $row->order_number,

                        'total' =>
                            (float) $row->total,

                        'ordered_at' =>
                            (string) $row->ordered_at,

                        'delivered_at' =>
                            (string) $row->delivered_at,

                        'customer' => [
                            'id' =>
                                (int) $row->customer_id,

                            'name' =>
                                trim(
                                    (string) $row->first_name
                                    . ' '
                                    . (string) $row->last_name
                                ),

                            'email' =>
                                $row->email !== null
                                    ? (string) $row->email
                                    : null,

                            'phone' =>
                                $row->phone !== null
                                    ? (string) $row->phone
                                    : null,
                        ],
                    ];
                },
            )
            ->values()
            ->all();
    }
}
