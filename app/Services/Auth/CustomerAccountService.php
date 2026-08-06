<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerAccountService
{
    public function customerRoleId(): ?int
    {
        $roleId = Role::query()
            ->where(
                'role_name',
                'customer',
            )
            ->value(
                'id',
            );

        return $roleId === null
            ? null
            : (int) $roleId;
    }

    /**
     * @param array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     email: string,
     *     password: string
     * } $data
     */
    public function registerWithPassword(
        int $customerRoleId,
        array $data,
    ): User {
        return DB::transaction(
            static function () use (
                $customerRoleId,
                $data,
            ): User {
                return User::query()->create([
                    'role_id' => $customerRoleId,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],

                    /*
                     * User utiliza el cast "hashed", por lo que Laravel
                     * almacenará la contraseña de forma segura.
                     */
                    'password' => $data['password'],

                    'is_active' => true,
                ]);
            },
            attempts: 3,
        );
    }

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

    public function findOrCreateFromGoogle(
        int $customerRoleId,
        string $email,
        string $firstName,
        string $lastName,
        string $phone,
    ): User {
        return DB::transaction(
            static function () use (
                $customerRoleId,
                $email,
                $firstName,
                $lastName,
                $phone,
            ): User {
                $user = User::query()
                    ->where(
                        'email',
                        $email,
                    )
                    ->lockForUpdate()
                    ->first();

                if ($user !== null) {
                    return $user;
                }

                return User::query()->create([
                    'email' => $email,
                    'role_id' => $customerRoleId,

                    'first_name' => $firstName !== ''
                        ? $firstName
                        : 'Cliente',

                    'last_name' => $lastName !== ''
                        ? $lastName
                        : 'Google',

                    'phone' => $phone,

                    /*
                     * Las cuentas Google no utilizan contraseña local.
                     * Se almacena una credencial aleatoria desconocida
                     * para el cliente.
                     */
                    'password' => Str::password(
                        length: 64,
                    ),

                    'is_active' => true,
                ]);
            },
            attempts: 3,
        );
    }
}
