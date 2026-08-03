<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentReceiptStatus;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PaymentReceiptService
{
    public const DISK = 'payment_receipts';

    public function storeForCustomer(
        int $orderId,
        User $user,
        UploadedFile $file,
    ): PaymentReceipt {
        $storedPath = null;

        try {
            return DB::transaction(
                function () use (
                    $orderId,
                    $user,
                    $file,
                    &$storedPath,
                ): PaymentReceipt {
                    $order = Order::query()
                        ->with([
                            'paymentMethod:id,name',
                            'orderStatus:id,status_name',
                        ])
                        ->whereKey($orderId)
                        ->where(
                            'user_id',
                            (int) $user->id,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertTransferOrder(
                        $order,
                    );

                    $this->assertOrderAcceptsReceipt(
                        $order,
                    );

                    $latestReceipt =
                        PaymentReceipt::query()
                            ->where(
                                'order_id',
                                (int) $order->id,
                            )
                            ->latest(
                                'submitted_at',
                            )
                            ->latest('id')
                            ->lockForUpdate()
                            ->first();

                    if ($latestReceipt !== null) {
                        if (
                            $latestReceipt->status
                            === PaymentReceiptStatus::Pending
                        ) {
                            throw ValidationException::withMessages([
                                'receipt' => [
                                    'Ya existe un comprobante pendiente de revisión para este pedido.',
                                ],
                            ]);
                        }

                        if (
                            $latestReceipt->status
                            === PaymentReceiptStatus::Approved
                        ) {
                            throw ValidationException::withMessages([
                                'receipt' => [
                                    'El comprobante de este pedido ya fue aprobado.',
                                ],
                            ]);
                        }
                    }

                    $uuid = (string) Str::uuid();

                    $extension = strtolower(
                        $file->guessExtension()
                        ?: $file->getClientOriginalExtension()
                        ?: 'bin',
                    );

                    $directory = sprintf(
                        '%s/%s/order-%d',
                        now()->format('Y'),
                        now()->format('m'),
                        (int) $order->id,
                    );

                    $storedPath = $file->storeAs(
                        $directory,
                        $uuid.'.'.$extension,
                        self::DISK,
                    );

                    if (
                        ! is_string($storedPath)
                        || $storedPath === ''
                    ) {
                        throw ValidationException::withMessages([
                            'receipt' => [
                                'No fue posible almacenar el comprobante. Inténtalo nuevamente.',
                            ],
                        ]);
                    }

                    $receipt =
                        PaymentReceipt::query()
                            ->create([
                                'uuid' =>
                                    $uuid,

                                'order_id' =>
                                    (int) $order->id,

                                'user_id' =>
                                    (int) $user->id,

                                'disk' =>
                                    self::DISK,

                                'file_path' =>
                                    $storedPath,

                                'original_name' =>
                                    Str::limit(
                                        basename(
                                            $file
                                                ->getClientOriginalName(),
                                        ),
                                        255,
                                        '',
                                    ),

                                'mime_type' =>
                                    (string) $file
                                        ->getMimeType(),

                                'file_size' =>
                                    (int) $file
                                        ->getSize(),

                                'status' =>
                                    PaymentReceiptStatus::Pending,

                                'submitted_at' =>
                                    now(),
                            ]);

                    return $receipt->load(
                        'reviewer',
                    );
                },
                attempts: 1,
            );
        } catch (Throwable $exception) {
            if (
                is_string($storedPath)
                && $storedPath !== ''
            ) {
                Storage::disk(
                    self::DISK,
                )->delete(
                    $storedPath,
                );
            }

            throw $exception;
        }
    }

    public function latestForCustomer(
        int $orderId,
        User $user,
    ): ?PaymentReceipt {
        Order::query()
            ->whereKey($orderId)
            ->where(
                'user_id',
                (int) $user->id,
            )
            ->firstOrFail();

        return PaymentReceipt::query()
            ->with(
                'reviewer:id,first_name,last_name',
            )
            ->where(
                'order_id',
                $orderId,
            )
            ->latest(
                'submitted_at',
            )
            ->latest('id')
            ->first();
    }

    public function historyForCustomer(
        int $orderId,
        User $user,
    ): Collection {
        Order::query()
            ->whereKey($orderId)
            ->where(
                'user_id',
                (int) $user->id,
            )
            ->firstOrFail();

        return PaymentReceipt::query()
            ->with(
                'reviewer:id,first_name,last_name',
            )
            ->where(
                'order_id',
                $orderId,
            )
            ->latest(
                'submitted_at',
            )
            ->latest('id')
            ->get();
    }

    public function paginatePending(
        int $perPage = 15,
    ): LengthAwarePaginator {
        return PaymentReceipt::query()
            ->with([
                'order:id,order_number,user_id,total,payment_method_id,ordered_at',

                'order.user:id,first_name,last_name,email,phone',

                'order.paymentMethod:id,name',

                'user:id,first_name,last_name,email,phone',

                'reviewer:id,first_name,last_name',
            ])
            ->where(
                'status',
                PaymentReceiptStatus::Pending->value,
            )
            ->latest(
                'submitted_at',
            )
            ->paginate(
                min(
                    max(
                        $perPage,
                        1,
                    ),
                    100,
                ),
            );
    }

    public function approve(
        string $receiptUuid,
        User $reviewer,
    ): PaymentReceipt {
        return DB::transaction(
            function () use (
                $receiptUuid,
                $reviewer,
            ): PaymentReceipt {
                $receipt =
                    PaymentReceipt::query()
                        ->where(
                            'uuid',
                            $receiptUuid,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertPending(
                    $receipt,
                );

                $receipt->forceFill([
                    'status' =>
                        PaymentReceiptStatus::Approved,

                    'rejection_reason' =>
                        null,

                    'reviewed_at' =>
                        now(),

                    'reviewed_by' =>
                        (int) $reviewer->id,

                    'expires_at' =>
                        now()->addDays(90),
                ])->save();

                return $receipt->load([
                    'order.user',
                    'order.paymentMethod',
                    'reviewer',
                ]);
            },
            attempts: 3,
        );
    }

    public function reject(
        string $receiptUuid,
        User $reviewer,
        string $reason,
    ): PaymentReceipt {
        return DB::transaction(
            function () use (
                $receiptUuid,
                $reviewer,
                $reason,
            ): PaymentReceipt {
                $receipt =
                    PaymentReceipt::query()
                        ->where(
                            'uuid',
                            $receiptUuid,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertPending(
                    $receipt,
                );

                $receipt->forceFill([
                    'status' =>
                        PaymentReceiptStatus::Rejected,

                    'rejection_reason' =>
                        trim($reason),

                    'reviewed_at' =>
                        now(),

                    'reviewed_by' =>
                        (int) $reviewer->id,

                    'expires_at' =>
                        now()->addDays(30),
                ])->save();

                return $receipt->load([
                    'order.user',
                    'order.paymentMethod',
                    'reviewer',
                ]);
            },
            attempts: 3,
        );
    }

    public function findVisibleFile(
        string $receiptUuid,
        User $user,
    ): PaymentReceipt {
        $receipt =
            PaymentReceipt::query()
                ->with(
                    'order:id,user_id',
                )
                ->where(
                    'uuid',
                    $receiptUuid,
                )
                ->firstOrFail();

        $role = strtolower(
            (string) $user
                ->role
                ?->role_name,
        );

        $isStaff = in_array(
            $role,
            [
                'operator',
                'admin',
            ],
            true,
        );

        $isOwner =
            (int) $receipt
                ->order
                ?->user_id
            === (int) $user->id;

        abort_unless(
            $isStaff || $isOwner,
            403,
            'No tienes permiso para consultar este comprobante.',
        );

        abort_unless(
            $receipt->hasStoredFile(),
            404,
            'El archivo del comprobante ya no está disponible.',
        );

        abort_unless(
            Storage::disk(
                $receipt->disk,
            )->exists(
                (string) $receipt->file_path,
            ),
            404,
            'El archivo del comprobante no existe en el almacenamiento.',
        );

        return $receipt;
    }

    public function pruneExpiredFiles(): int
    {
        $deleted = 0;

        PaymentReceipt::query()
            ->whereNull(
                'file_deleted_at',
            )
            ->whereNotNull(
                'file_path',
            )
            ->whereNotNull(
                'expires_at',
            )
            ->where(
                'expires_at',
                '<=',
                now(),
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function (
                    Collection $receipts,
                ) use (
                    &$deleted,
                ): void {
                    foreach (
                        $receipts as $receipt
                    ) {
                        $path =
                            (string) $receipt
                                ->file_path;

                        $disk =
                            (string) $receipt
                                ->disk;

                        if ($path !== '') {
                            Storage::disk(
                                $disk,
                            )->delete(
                                $path,
                            );
                        }

                        $receipt->forceFill([
                            'file_path' =>
                                null,

                            'file_deleted_at' =>
                                now(),
                        ])->save();

                        $deleted++;
                    }
                },
            );

        return $deleted;
    }

    private function assertTransferOrder(
        Order $order,
    ): void {
        if (
            strtolower(
                (string) $order
                    ->paymentMethod
                    ?->name,
            ) !== 'transfer'
        ) {
            throw ValidationException::withMessages([
                'receipt' => [
                    'Solo se puede subir un comprobante para pedidos pagados mediante transferencia.',
                ],
            ]);
        }
    }

    private function assertOrderAcceptsReceipt(
        Order $order,
    ): void {
        $status = strtolower(
            (string) $order
                ->orderStatus
                ?->status_name,
        );

        if (
            in_array(
                $status,
                [
                    'cancelled',
                    'delivered',
                ],
                true,
            )
        ) {
            throw ValidationException::withMessages([
                'receipt' => [
                    'Este pedido ya no acepta nuevos comprobantes.',
                ],
            ]);
        }
    }

    private function assertPending(
        PaymentReceipt $receipt,
    ): void {
        if (
            $receipt->status
            !== PaymentReceiptStatus::Pending
        ) {
            throw ValidationException::withMessages([
                'receipt' => [
                    'El comprobante ya fue revisado y no puede modificarse nuevamente.',
                ],
            ]);
        }
    }
}
