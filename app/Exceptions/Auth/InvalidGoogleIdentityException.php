<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use RuntimeException;

final class InvalidGoogleIdentityException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missingEmail(): self
    {
        return new self(
            errorCode: 'GOOGLE_EMAIL_REQUIRED',
            message: 'La cuenta de Google no proporcionó un correo válido.',
        );
    }

    public static function missingUid(): self
    {
        return new self(
            errorCode: 'GOOGLE_UID_REQUIRED',
            message: 'No fue posible identificar la cuenta de Google.',
        );
    }
}
