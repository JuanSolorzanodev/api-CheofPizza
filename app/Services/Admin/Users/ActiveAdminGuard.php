<?php

declare(strict_types=1);

namespace App\Services\Admin\Users;

use App\Exceptions\Admin\LastActiveAdminException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ActiveAdminGuard
{
    /**
     * Impide quitar el rol al último administrador activo.
     *
     * Debe ejecutarse dentro de una transacción.
     */
    public function ensureRoleCanBeChanged(
        User $user,
        Role $newRole,
    ): void {
        if (! $this->isActiveAdmin($user)) {
            return;
        }

        if (
            strtolower((string) $newRole->role_name) ===
            'admin'
        ) {
            return;
        }

        if ($this->activeAdministratorsCount() <= 1) {
            throw new LastActiveAdminException(
                'No puedes quitar el rol al último administrador activo.',
            );
        }
    }

    /**
     * Impide desactivar al último administrador activo.
     *
     * Debe ejecutarse dentro de una transacción.
     */
    public function ensureCanBeDisabled(
        User $user,
        bool $newStatus,
    ): void {
        if ($newStatus) {
            return;
        }

        if (! $this->isActiveAdmin($user)) {
            return;
        }

        if ($this->activeAdministratorsCount() <= 1) {
            throw new LastActiveAdminException(
                'No puedes bloquear al último administrador activo.',
            );
        }
    }

    /**
     * Determina si el usuario es un administrador activo.
     */
    private function isActiveAdmin(
        User $user,
    ): bool {
        return (bool) $user->is_active
            && strtolower(
                (string) $user->role?->role_name,
            ) === 'admin';
    }

    /**
     * Cuenta los administradores activos y bloquea las filas seleccionadas.
     *
     * Se utiliza pluck() para que lockForUpdate() aplique sobre registros
     * concretos dentro de la transacción.
     */
    private function activeAdministratorsCount(): int
    {
        return User::query()
            ->where(
                'is_active',
                true,
            )
            ->whereHas(
                'role',
                fn (
                    Builder $query,
                ): Builder => $query->where(
                    'role_name',
                    'admin',
                ),
            )
            ->lockForUpdate()
            ->pluck('users.id')
            ->count();
    }
}
