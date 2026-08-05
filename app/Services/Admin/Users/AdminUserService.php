<?php

declare(strict_types=1);

namespace App\Services\Admin\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminUserService
{
    public function __construct(
        private readonly ActiveAdminGuard $activeAdminGuard,
    ) {}

    /**
     * Crea un usuario desde el panel administrativo.
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string|null,
     *     email: string,
     *     role: string,
     *     is_active: bool
     * }  $data
     */
    public function create(
        array $data,
    ): ?User {
        $role = $this->findRole(
            $data['role'],
        );

        if ($role === null) {
            return null;
        }

        $user = User::query()->create([
            'role_id' => (int) $role->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Str::random(64),
            'is_active' => (bool) $data['is_active'],
        ]);

        return $this->loadRelations(
            $user,
        );
    }

    /**
     * Actualiza los datos personales de un usuario.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(
        User $user,
        array $data,
    ): User {
        $user
            ->fill($data)
            ->save();

        return $this->loadRelations(
            $user,
        );
    }

    /**
     * Cambia el rol y revoca todas las sesiones existentes.
     */
    public function changeRole(
        User $user,
        string $roleName,
    ): ?User {
        $newRole = $this->findRole(
            $roleName,
        );

        if ($newRole === null) {
            return null;
        }

        DB::transaction(
            function () use (
                $user,
                $newRole,
            ): void {
                $lockedUser = User::query()
                    ->with('role')
                    ->lockForUpdate()
                    ->findOrFail(
                        $user->id,
                    );

                $this->activeAdminGuard
                    ->ensureRoleCanBeChanged(
                        user: $lockedUser,
                        newRole: $newRole,
                    );

                $lockedUser
                    ->forceFill([
                        'role_id' => (int) $newRole->id,
                    ])
                    ->save();

                $lockedUser
                    ->tokens()
                    ->delete();
            },
            attempts: 3,
        );

        $user->refresh();

        return $this->loadRelations(
            $user,
        );
    }

    /**
     * Activa o bloquea un usuario.
     *
     * Al bloquearlo se revocan todas sus sesiones.
     */
    public function changeStatus(
        User $user,
        bool $isActive,
    ): User {
        DB::transaction(
            function () use (
                $user,
                $isActive,
            ): void {
                $lockedUser = User::query()
                    ->with('role')
                    ->lockForUpdate()
                    ->findOrFail(
                        $user->id,
                    );

                $this->activeAdminGuard
                    ->ensureCanBeDisabled(
                        user: $lockedUser,
                        newStatus: $isActive,
                    );

                $lockedUser
                    ->forceFill([
                        'is_active' => $isActive,
                    ])
                    ->save();

                if (! $isActive) {
                    $lockedUser
                        ->tokens()
                        ->delete();
                }
            },
            attempts: 3,
        );

        $user->refresh();

        return $this->loadRelations(
            $user,
        );
    }

    /**
     * Carga las relaciones y contadores usados por AdminUserResource.
     */
    public function loadRelations(
        User $user,
    ): User {
        $user
            ->load('role')
            ->loadCount([
                'carts',
                'orders',
                'payments',
            ]);

        return $user;
    }

    private function findRole(
        string $roleName,
    ): ?Role {
        return Role::query()
            ->where(
                'role_name',
                strtolower(
                    trim($roleName),
                ),
            )
            ->first();
    }
}
