<?php

declare(strict_types=1);

namespace App\Services\Admin\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AdminUserQueryService
{
    /**
     * Recupera los usuarios administrativos aplicando búsqueda,
     * rol, estado y paginación.
     *
     * @param  array{
     *     search?: mixed,
     *     role?: mixed,
     *     status?: mixed,
     *     per_page?: mixed
     * }  $filters
     * @return LengthAwarePaginator<User>
     */
    public function paginate(
        array $filters,
    ): LengthAwarePaginator {
        $search = trim(
            (string) ($filters['search'] ?? ''),
        );

        $role = strtolower(
            trim(
                (string) ($filters['role'] ?? ''),
            ),
        );

        $status = strtolower(
            trim(
                (string) ($filters['status'] ?? ''),
            ),
        );

        $perPage = max(
            5,
            min(
                100,
                (int) ($filters['per_page'] ?? 15),
            ),
        );

        return User::query()
            ->with('role')
            ->withCount([
                'carts',
                'orders',
                'payments',
            ])
            ->when(
                $search !== '',
                function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        function (
                            Builder $inner,
                        ) use ($search): void {
                            $likeSearch = "%{$search}%";

                            $inner
                                ->where(
                                    'first_name',
                                    'like',
                                    $likeSearch,
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    $likeSearch,
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    $likeSearch,
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    $likeSearch,
                                )
                                ->orWhereRaw(
                                    "CONCAT(first_name, ' ', last_name) LIKE ?",
                                    [
                                        $likeSearch,
                                    ],
                                );
                        },
                    );
                },
            )
            ->when(
                in_array(
                    $role,
                    [
                        'customer',
                        'operator',
                        'admin',
                    ],
                    true,
                ),
                fn (Builder $query): Builder => $query->whereHas(
                    'role',
                    fn (
                        Builder $roleQuery,
                    ): Builder => $roleQuery->where(
                        'role_name',
                        $role,
                    ),
                ),
            )
            ->when(
                $status === 'active',
                fn (Builder $query): Builder => $query->where(
                    'is_active',
                    true,
                ),
            )
            ->when(
                $status === 'inactive',
                fn (Builder $query): Builder => $query->where(
                    'is_active',
                    false,
                ),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Devuelve los roles administrables con sus etiquetas públicas.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     label: string
     * }>
     */
    public function roles(): Collection
    {
        return Role::query()
            ->whereIn(
                'role_name',
                [
                    'customer',
                    'operator',
                    'admin',
                ],
            )
            ->orderByRaw(
                "
                CASE role_name
                    WHEN 'admin' THEN 1
                    WHEN 'operator' THEN 2
                    WHEN 'customer' THEN 3
                    ELSE 4
                END
                ",
            )
            ->get()
            ->map(
                static fn (
                    Role $role,
                ): array => [
                    'id' => (int) $role->id,
                    'name' => (string) $role->role_name,
                    'label' => match ($role->role_name) {
                        'admin' => 'Administrador',
                        'operator' => 'Operador',
                        default => 'Cliente',
                    },
                ],
            )
            ->values();
    }
}
