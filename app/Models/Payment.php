<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'idempotency_key',

        'user_id',
        'cart_id',
        'order_id',

        'provider',
        'provider_order_id',
        'provider_capture_id',
        'provider_status',

        'amount',
        'currency',
        'status',

        'failure_code',
        'failure_message',

        'provider_metadata',
        'checkout_context',
        'cart_fingerprint',

        'approved_at',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'refunded_at',
    ];

    /**
     * Información técnica interna que no debe exponerse
     * automáticamente mediante respuestas JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'provider_metadata',
        'checkout_context',
        'cart_fingerprint',
        'idempotency_key',
        'failure_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cart_id' => 'integer',
            'order_id' => 'integer',

            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,

            /*
             * Laravel mantiene DECIMAL como string.
             * Evitaremos cálculos financieros con float.
             */
            'amount' => 'decimal:2',

            'provider_metadata' => 'array',
            'checkout_context' => 'array',

            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (blank($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }

            if (blank($payment->idempotency_key)) {
                $payment->idempotency_key = (string) Str::uuid();
            }

            if (blank($payment->currency)) {
                $payment->currency = strtoupper(
                    trim(
                        (string) config(
                            'paypal.currency',
                            'USD'
                        )
                    )
                );
            }

            if ($payment->provider === null) {
                $payment->provider =
                    PaymentProvider::PAYPAL;
            }

            if ($payment->status === null) {
                $payment->status =
                    PaymentStatus::CREATED;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(
            Cart::class,
            'cart_id'
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'order_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCompleted(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            PaymentStatus::COMPLETED->value
        );
    }

    public function scopePending(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            [
                PaymentStatus::CREATED->value,
                PaymentStatus::PENDING->value,
                PaymentStatus::APPROVED->value,
            ]
        );
    }

    public function scopeForProvider(
        Builder $query,
        PaymentProvider $provider,
    ): Builder {
        return $query->where(
            'provider',
            $provider->value
        );
    }

    public function scopeForUser(
        Builder $query,
        int $userId,
    ): Builder {
        return $query->where(
            'user_id',
            $userId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    public function isCreated(): bool
    {
        return $this->status ===
            PaymentStatus::CREATED;
    }

    public function isPending(): bool
    {
        return in_array(
            $this->status,
            [
                PaymentStatus::CREATED,
                PaymentStatus::PENDING,
                PaymentStatus::APPROVED,
            ],
            true
        );
    }

    public function isApproved(): bool
    {
        return $this->status ===
            PaymentStatus::APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status ===
            PaymentStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return in_array(
            $this->status,
            [
                PaymentStatus::FAILED,
                PaymentStatus::DENIED,
            ],
            true
        );
    }

    public function isCancelled(): bool
    {
        return $this->status ===
            PaymentStatus::CANCELLED;
    }

    public function isRefunded(): bool
    {
        return in_array(
            $this->status,
            [
                PaymentStatus::REFUNDED,
                PaymentStatus::PARTIALLY_REFUNDED,
            ],
            true
        );
    }

    public function canBeCaptured(): bool
    {
        return in_array(
            $this->status,
            [
                PaymentStatus::PENDING,
                PaymentStatus::APPROVED,
            ],
            true
        );
    }

    public function canBeCancelled(): bool
    {
        return in_array(
            $this->status,
            [
                PaymentStatus::CREATED,
                PaymentStatus::PENDING,
                PaymentStatus::APPROVED,
            ],
            true
        );
    }

    public function canBeRefunded(): bool
    {
        return $this->status ===
            PaymentStatus::COMPLETED;
    }

    /*
    |--------------------------------------------------------------------------
    | State transitions
    |--------------------------------------------------------------------------
    */

    /**
     * Marca que PayPal está esperando la aprobación del comprador.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsPending(
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        if ($this->isCompleted()) {
            throw new DomainException(
                'Un pago completado no puede volver al estado pendiente.'
            );
        }

        $this->forceFill([
            'status' => PaymentStatus::PENDING,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'failure_code' => null,
            'failure_message' => null,
            'failed_at' => null,
        ])->save();
    }

    /**
     * Marca que el comprador aprobó la orden PayPal.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsApproved(
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        if ($this->isCompleted()) {
            /*
             * Una repetición de webhook o callback no debe degradar
             * un pago que ya fue completado.
             */
            return;
        }

        if (
            ! in_array(
                $this->status,
                [
                    PaymentStatus::CREATED,
                    PaymentStatus::PENDING,
                    PaymentStatus::APPROVED,
                ],
                true
            )
        ) {
            throw new DomainException(
                sprintf(
                    'El pago no puede aprobarse desde el estado [%s].',
                    $this->status->value
                )
            );
        }

        $this->forceFill([
            'status' => PaymentStatus::APPROVED,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'approved_at' =>
                $this->approved_at ?? now(),

            'failure_code' => null,
            'failure_message' => null,
            'failed_at' => null,
        ])->save();
    }

    /**
     * Marca la captura como completada.
     *
     * Este método es idempotente cuando recibe nuevamente
     * el mismo capture ID.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsCompleted(
        string $captureId,
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        $captureId = trim($captureId);

        if ($captureId === '') {
            throw new DomainException(
                'El identificador de captura no puede estar vacío.'
            );
        }

        if ($this->isCompleted()) {
            if (
                $this->provider_capture_id ===
                $captureId
            ) {
                return;
            }

            throw new DomainException(
                'El pago ya fue completado con otra captura.'
            );
        }

        if (! $this->canBeCaptured()) {
            throw new DomainException(
                sprintf(
                    'El pago no puede completarse desde el estado [%s].',
                    $this->status->value
                )
            );
        }

        $this->forceFill([
            'provider_capture_id' => $captureId,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'status' => PaymentStatus::COMPLETED,

            /*
             * Si la respuesta de captura llega directamente sin que
             * previamente hayamos persistido APPROVED, mantenemos una
             * trazabilidad temporal razonable.
             */
            'approved_at' =>
                $this->approved_at ?? now(),

            'paid_at' =>
                $this->paid_at ?? now(),

            'failure_code' => null,
            'failure_message' => null,
            'failed_at' => null,
            'cancelled_at' => null,
        ])->save();
    }

    /**
     * Marca una operación como rechazada por el proveedor.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsDenied(
        ?string $code,
        string $message,
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        if ($this->isCompleted()) {
            throw new DomainException(
                'Un pago completado no puede marcarse como rechazado.'
            );
        }

        $this->forceFill([
            'status' => PaymentStatus::DENIED,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'failure_code' => $code,

            'failure_message' => $message,

            'failed_at' =>
                $this->failed_at ?? now(),
        ])->save();
    }

    /**
     * Marca un fallo técnico o inesperado.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsFailed(
        ?string $code,
        string $message,
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        /*
         * Nunca degradamos un pago completado por un error posterior
         * de infraestructura, persistencia o respuesta HTTP.
         */
        if ($this->isCompleted()) {
            throw new DomainException(
                'Un pago completado no puede marcarse como fallido.'
            );
        }

        $this->forceFill([
            'status' => PaymentStatus::FAILED,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'failure_code' => $code,

            'failure_message' => $message,

            'failed_at' =>
                $this->failed_at ?? now(),
        ])->save();
    }

    /**
     * Marca la operación como cancelada antes de la captura.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsCancelled(
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        if (! $this->canBeCancelled()) {
            throw new DomainException(
                sprintf(
                    'El pago no puede cancelarse desde el estado [%s].',
                    $this->status->value
                )
            );
        }

        $this->forceFill([
            'status' => PaymentStatus::CANCELLED,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'cancelled_at' =>
                $this->cancelled_at ?? now(),
        ])->save();
    }

    /**
     * Marca que el importe fue reembolsado completamente.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsRefunded(
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        if (
            ! $this->canBeRefunded()
            && $this->status !==
                PaymentStatus::REFUNDED
        ) {
            throw new DomainException(
                sprintf(
                    'El pago no puede reembolsarse desde el estado [%s].',
                    $this->status->value
                )
            );
        }

        if (
            $this->status ===
            PaymentStatus::REFUNDED
        ) {
            return;
        }

        $this->forceFill([
            'status' => PaymentStatus::REFUNDED,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'refunded_at' =>
                $this->refunded_at ?? now(),
        ])->save();
    }

    /**
     * Marca que solo una parte de la captura fue reembolsada.
     *
     * @param array<string, mixed>|null $providerMetadata
     */
    public function markAsPartiallyRefunded(
        ?string $providerStatus = null,
        ?array $providerMetadata = null,
    ): void {
        if (
            ! in_array(
                $this->status,
                [
                    PaymentStatus::COMPLETED,
                    PaymentStatus::PARTIALLY_REFUNDED,
                ],
                true
            )
        ) {
            throw new DomainException(
                sprintf(
                    'El pago no puede reembolsarse parcialmente desde [%s].',
                    $this->status->value
                )
            );
        }

        $this->forceFill([
            'status' =>
                PaymentStatus::PARTIALLY_REFUNDED,

            'provider_status' =>
                $providerStatus
                ?? $this->provider_status,

            'provider_metadata' =>
                $this->mergeProviderMetadata(
                    $providerMetadata
                ),

            'refunded_at' =>
                $this->refunded_at ?? now(),
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Internal helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Conserva los metadatos anteriores y agrega únicamente
     * los nuevos datos normalizados del proveedor.
     *
     * @param array<string, mixed>|null $metadata
     *
     * @return array<string, mixed>|null
     */
    private function mergeProviderMetadata(
        ?array $metadata
    ): ?array {
        $current = is_array(
            $this->provider_metadata
        )
            ? $this->provider_metadata
            : [];

        if ($metadata === null) {
            return $current !== []
                ? $current
                : null;
        }

        return array_replace_recursive(
            $current,
            $metadata
        );
    }
}
