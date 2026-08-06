<?php

declare(strict_types=1);

namespace App\Services\Admin\CashRegister;

use App\Http\Resources\Api\V1\Admin\CashRegister\CashMovementResource;
use App\Http\Resources\Api\V1\Admin\CashRegister\CashSessionResource;
use App\Models\CashSession;

final class CashSessionDetailService
{
    public function __construct(
        private readonly CashSessionSummaryService $summaryService,
        private readonly CashOrderCollectionQueryService $cashOrderQueryService,
    ) {}

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
            'session' => (new CashSessionResource($session))
                ->toArray(request()),

            'summary' => $this->summaryService->get($session),

            'cash_orders' => $this->cashOrders($session),

            'movements' => CashMovementResource::collection(
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
        CashSession $session,
    ): array {
        $endAt = $session->closed_at
            ?? now();

        return $this->cashOrderQueryService
            ->details(
                session: $session,
                endAt: $endAt,
            )
            ->map(
                static function (object $row): array {
                    return [
                        'id' => (int) $row->id,

                        'order_number' => (string) $row->order_number,

                        'total' => (float) $row->total,

                        'ordered_at' => (string) $row->ordered_at,

                        'delivered_at' => (string) $row->delivered_at,

                        'customer' => [
                            'id' => (int) $row->customer_id,

                            'name' => trim(
                                (string) $row->first_name
                                    .' '
                                    .(string) $row->last_name,
                            ),

                            'email' => $row->email !== null
                                ? (string) $row->email
                                : null,

                            'phone' => $row->phone !== null
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
