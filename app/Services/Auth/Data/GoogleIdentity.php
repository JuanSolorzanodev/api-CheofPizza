<?php

declare(strict_types=1);

namespace App\Services\Auth\Data;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $email,
        public string $firebaseUid,
        public string $firstName,
        public string $lastName,
    ) {}
}
