<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class PasswordAuthenticationService
{
    public function findByEmail(
        string $email,
    ): ?User {
        return User::query()
            ->where(
                'email',
                $email,
            )
            ->first();
    }

    public function credentialsAreValid(
        ?User $user,
        string $password,
    ): bool {
        if ($user === null) {
            return false;
        }

        return Hash::check(
            $password,
            (string) $user->password,
        );
    }
}
