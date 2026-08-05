<?php

declare(strict_types=1);

namespace App\Services\Admin\CashRegister;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\OrderStatusName;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CashRegisterService
{
    public function current(): ?CashSession
    {
        return CashSession::query()
            ->with([
                'openedBy',
                'closedBy',
            ])
            ->where(
                'status',
                CashSessionStatus::Open->value,
            )
            ->latest('opened_at')
            ->first();
    }

    /**
     * @param array{
     *     opening_amount: int|float|string,
     *     opening_note?: string|null
     * } $data
     */
    public function open(
        User $admin,
        array $data,
    ): CashSession {
        return DB::transaction(
            function () use ($admin, $data): CashSession {
                $existing = CashSession::query()
                    ->where(
                        'status',
                        CashSessionStatus::Open->value,
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw ValidationException::withMessages([
                        'cash_session' => 'Ya existe una caja abierta.',
                    ]);
                }

                $session = CashSession::query()->create([
                    'uuid' => (string) Str::uuid(),

                    'opened_by' => $admin->id,

                    'status' => CashSessionStatus::Open,

                    'opening_amount' => $this->money(
                        $data['opening_amount']
                    ),

                    'opened_at' => now(),

                    'opening_note' => $data['opening_note'] ?? null,
                ]);

                return $session->load([
                    'openedBy',
                    'closedBy',
                ]);
            },
            attempts: 3,
        );
    }

    /**
     * @param array{
     *     type: string,
     *     amount: int|float|string,
     *     reason: string
     * } $data
     */
    public function addMovement(
        CashSession $session,
        User $admin,
        array $data,
    ): CashMovement {
        return DB::transaction(
            function () use ($session, $admin, $data): CashMovement {
                $locked = CashSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $locked->status
                    !== CashSessionStatus::Open
                ) {
                    throw ValidationException::withMessages([
                        'cash_session' => 'No se pueden registrar movimientos en una caja cerrada.',
                    ]);
                }

                $movement = CashMovement::query()->create([
                    'uuid' => (string) Str::uuid(),

                    'cash_session_id' => $locked->id,

                    'created_by' => $admin->id,

                    'type' => CashMovementType::from(
                        (string) $data['type']
                    ),

                    'amount' => $this->money(
                        $data['amount']
                    ),

                    'reason' => $data['reason'],

                    'occurred_at' => now(),
                ]);

                return $movement->load('createdBy');
            },
            attempts: 3,
        );
    }

    public function movements(
        CashSession $session,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $session
            ->movements()
            ->with('createdBy')
            ->latest('occurred_at')
            ->paginate($perPage);
    }

    /**
     * @param array{
     *     counted_cash: int|float|string,
     *     closing_note?: string|null
     * } $data
     */
    public function close(
        CashSession $session,
        User $admin,
        array $data,
    ): CashSession {
        return DB::transaction(
            function () use ($session, $admin, $data): CashSession {
                $locked = CashSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $locked->status
                    !== CashSessionStatus::Open
                ) {
                    throw ValidationException::withMessages([
                        'cash_session' => 'La caja ya está cerrada.',
                    ]);
                }

                $closedAt = now();

                $cashSales = $this->cashSalesBetween(
                    openedAt: $locked->opened_at,
                    closedAt: $closedAt,
                );

                $movementTotals = $this->movementTotals(
                    $locked
                );

                $expectedCash = bcsub(
                    bcadd(
                        bcadd(
                            (string) $locked->opening_amount,
                            $cashSales,
                            2,
                        ),
                        $movementTotals['income'],
                        2,
                    ),
                    $movementTotals['expense'],
                    2,
                );

                $countedCash = $this->money(
                    $data['counted_cash']
                );

                $difference = bcsub(
                    $countedCash,
                    $expectedCash,
                    2,
                );

                $locked->forceFill([
                    'closed_by' => $admin->id,

                    'status' => CashSessionStatus::Closed,

                    'expected_cash' => $expectedCash,

                    'counted_cash' => $countedCash,

                    'difference' => $difference,

                    'closed_at' => $closedAt,

                    'closing_note' => $data['closing_note'] ?? null,
                ])->save();

                return $locked->load([
                    'openedBy',
                    'closedBy',
                ]);
            },
            attempts: 3,
        );
    }

    /**
     * @param array{
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     status?: string|null,
     *     page: int,
     *     per_page: int
     * } $filters
     */
    public function history(
        array $filters,
    ): LengthAwarePaginator {
        $query = CashSession::query()
            ->with([
                'openedBy',
                'closedBy',
            ]);

        if (
            filled($filters['date_from'] ?? null)
            && filled($filters['date_to'] ?? null)
        ) {
            $from = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                (string) $filters['date_from'],
            )->startOfDay();

            $to = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                (string) $filters['date_to'],
            )->endOfDay();

            $query->whereBetween(
                'opened_at',
                [$from, $to],
            );
        }

        if (filled($filters['status'] ?? null)) {
            $query->where(
                'status',
                (string) $filters['status'],
            );
        }

        return $query
            ->latest('opened_at')
            ->paginate(
                perPage: (int) $filters['per_page'],
                columns: ['*'],
                pageName: 'page',
                page: (int) $filters['page'],
            );
    }

    /**
     * @return array{
     *     income: string,
     *     expense: string
     * }
     */
    private function movementTotals(
        CashSession $session,
    ): array {
        $totals = $session
            ->movements()
            ->selectRaw(
                'type, COALESCE(SUM(amount), 0) AS total'
            )
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'income' => $this->money(
                $totals[
                    CashMovementType::Income->value
                ] ?? 0
            ),

            'expense' => $this->money(
                $totals[
                    CashMovementType::Expense->value
                ] ?? 0
            ),
        ];
    }

    private function cashSalesBetween(
        mixed $openedAt,
        mixed $closedAt,
    ): string {
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
                [$openedAt, $closedAt],
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

        $amount = DB::table('orders')
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
            ->sum('orders.total');

        return $this->money($amount);
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
