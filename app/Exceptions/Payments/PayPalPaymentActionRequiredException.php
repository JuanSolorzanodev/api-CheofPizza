<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use RuntimeException;
use Throwable;

final class PayPalPaymentActionRequiredException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $action,
        public readonly string $paypalCode,
        public readonly ?string $reference = null,
        public readonly bool $recoverable = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }
}
