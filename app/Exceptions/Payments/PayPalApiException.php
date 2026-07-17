<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use RuntimeException;
use Throwable;

final class PayPalApiException extends RuntimeException
{
    /**
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $debugId = null,
        public readonly ?string $paypalErrorName = null,
        public readonly ?array $details = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            code: $statusCode ?? 0,
            previous: $previous,
        );
    }
}
