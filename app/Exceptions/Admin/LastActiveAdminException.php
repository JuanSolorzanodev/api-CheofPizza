<?php

declare(strict_types=1);

namespace App\Exceptions\Admin;

use RuntimeException;

final class LastActiveAdminException extends RuntimeException
{
    public function __construct(
        string $message = 'Debe existir al menos un administrador activo.',
    ) {
        parent::__construct($message);
    }
}
