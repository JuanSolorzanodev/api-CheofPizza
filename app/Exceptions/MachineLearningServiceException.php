<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class MachineLearningServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $remoteStatus = null,
        private readonly ?array $remotePayload = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    public function remoteStatus(): ?int
    {
        return $this->remoteStatus;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function remotePayload(): ?array
    {
        return $this->remotePayload;
    }
}
