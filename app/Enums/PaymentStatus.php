<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    /**
     * Registro local creado, pero todavía no existe una orden válida
     * confirmada por el proveedor.
     */
    case CREATED = 'created';

    /**
     * Orden creada en PayPal y esperando interacción del cliente.
     */
    case PENDING = 'pending';

    /**
     * El cliente aprobó la operación, pero todavía no se confirmó
     * definitivamente la captura.
     */
    case APPROVED = 'approved';

    /**
     * El dinero fue capturado correctamente.
     */
    case COMPLETED = 'completed';

    /**
     * El proveedor rechazó la operación.
     */
    case DENIED = 'denied';

    /**
     * Ocurrió un fallo técnico o de procesamiento.
     */
    case FAILED = 'failed';

    /**
     * La operación fue cancelada antes de completarse.
     */
    case CANCELLED = 'cancelled';

    /**
     * El pago fue devuelto completamente.
     */
    case REFUNDED = 'refunded';

    /**
     * Solo una parte del pago fue devuelta.
     */
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED,
            self::DENIED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED => true,

            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}
