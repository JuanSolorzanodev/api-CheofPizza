<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\StoreAdminUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserRoleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserStatusRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class UserController
{
    public function index(
        Request $request,
    ): JsonResponse {
        $search = trim(
            (string) $request->query(
                'search',
                '',
            ),
        );

        $role = strtolower(
            trim(
                (string) $request->query(
                    'role',
                    '',
                ),
            ),
        );

        $status = strtolower(
            trim(
                (string) $request->query(
                    'status',
                    '',
                ),
            ),
        );

        $perPage = max(
            5,
            min(
                100,
                (int) $request->integer(
                    'per_page',
                    15,
                ),
            ),
        );

        $users = User::query()
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
                            $inner
                                ->where(
                                    'first_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhereRaw(
                                    "CONCAT(first_name, ' ', last_name) LIKE ?",
                                    ["%{$search}%"],
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
                fn (Builder $query): Builder =>
                    $query->whereHas(
                        'role',
                        fn (
                            Builder $roleQuery,
                        ): Builder =>
                            $roleQuery->where(
                                'role_name',
                                $role,
                            ),
                    ),
            )
            ->when(
                $status === 'active',
                fn (Builder $query): Builder =>
                    $query->where(
                        'is_active',
                        true,
                    ),
            )
            ->when(
                $status === 'inactive',
                fn (Builder $query): Builder =>
                    $query->where(
                        'is_active',
                        false,
                    ),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::success(
            data: AdminUserResource::collection(
                $users->getCollection(),
            ),

            message:
                'Usuarios recuperados correctamente.',

            meta: [
                'current_page' =>
                    $users->currentPage(),

                'last_page' =>
                    $users->lastPage(),

                'per_page' =>
                    $users->perPage(),

                'total' =>
                    $users->total(),

                'from' =>
                    $users->firstItem(),

                'to' =>
                    $users->lastItem(),
            ],
        );
    }

    public function roles(): JsonResponse
    {
        $roles = Role::query()
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
                    'id' =>
                        (int) $role->id,

                    'name' =>
                        (string) $role->role_name,

                    'label' => match (
                        $role->role_name
                    ) {
                        'admin' =>
                            'Administrador',

                        'operator' =>
                            'Operador',

                        default =>
                            'Cliente',
                    },
                ],
            )
            ->values();

        return ApiResponse::success(
            data: $roles,
            message:
                'Roles recuperados correctamente.',
        );
    }

    public function store(
        StoreAdminUserRequest $request,
    ): JsonResponse {
        $data = $request->validated();

        $roleId = Role::query()
            ->where(
                'role_name',
                $data['role'],
            )
            ->value('id');

        if ($roleId === null) {
            return ApiResponse::error(
                message:
                    'El rol seleccionado no existe.',
                status:
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                code:
                    'ROLE_NOT_FOUND',
            );
        }

        $user = User::query()->create([
            'role_id' =>
                (int) $roleId,

            'first_name' =>
                $data['first_name'],

            'last_name' =>
                $data['last_name'],

            'phone' =>
                $data['phone'],

            'email' =>
                $data['email'],

            /*
             * La autenticación actual usa Google/Firebase.
             * La contraseña queda aleatoria y no se comparte.
             */
            'password' =>
                Str::random(64),

            'is_active' =>
                (bool) $data['is_active'],
        ]);

        $user
            ->load('role')
            ->loadCount([
                'carts',
                'orders',
                'payments',
            ]);

        return ApiResponse::success(
            data:
                new AdminUserResource(
                    $user,
                ),

            message:
                'Usuario creado correctamente.',

            status:
                Response::HTTP_CREATED,
        );
    }

    public function show(
        User $user,
    ): JsonResponse {
        $user
            ->load('role')
            ->loadCount([
                'carts',
                'orders',
                'payments',
            ]);

        return ApiResponse::success(
            data:
                new AdminUserResource(
                    $user,
                ),

            message:
                'Usuario recuperado correctamente.',
        );
    }

    public function update(
        UpdateAdminUserRequest $request,
        User $user,
    ): JsonResponse {
        $user->fill(
            $request->validated(),
        )->save();

        $user
            ->load('role')
            ->loadCount([
                'carts',
                'orders',
                'payments',
            ]);

        return ApiResponse::success(
            data:
                new AdminUserResource(
                    $user,
                ),

            message:
                'Usuario actualizado correctamente.',
        );
    }

    public function updateRole(
        UpdateAdminUserRoleRequest $request,
        User $user,
    ): JsonResponse {
        $authenticatedUser =
            $request->user();

        if (
            (int) $authenticatedUser->id ===
            (int) $user->id
        ) {
            return ApiResponse::error(
                message:
                    'No puedes cambiar tu propio rol.',
                status:
                    Response::HTTP_CONFLICT,
                code:
                    'CANNOT_CHANGE_OWN_ROLE',
            );
        }

        $newRoleName =
            $request->string(
                'role',
            )->toString();

        $newRole = Role::query()
            ->where(
                'role_name',
                $newRoleName,
            )
            ->first();

        if ($newRole === null) {
            return ApiResponse::error(
                message:
                    'El rol seleccionado no existe.',
                status:
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                code:
                    'ROLE_NOT_FOUND',
            );
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

                $currentRole =
                    strtolower(
                        (string) $lockedUser
                            ->role
                            ?->role_name,
                    );

                if (
                    $currentRole === 'admin' &&
                    $newRole->role_name !== 'admin'
                ) {
                    $activeAdmins =
                        User::query()
                            ->where(
                                'is_active',
                                true,
                            )
                            ->whereHas(
                                'role',
                                fn (
                                    Builder $query,
                                ): Builder =>
                                    $query->where(
                                        'role_name',
                                        'admin',
                                    ),
                            )
                            ->lockForUpdate()
                            ->count();

                    if ($activeAdmins <= 1) {
                        abort(
                            Response::HTTP_CONFLICT,
                            'No puedes quitar el rol al último administrador activo.',
                        );
                    }
                }

                $lockedUser->forceFill([
                    'role_id' =>
                        (int) $newRole->id,
                ])->save();

                /*
                 * Revoca sesiones previas cuando cambia el rol.
                 */
                $lockedUser->tokens()->delete();
            },
            attempts: 3,
        );

        $user
            ->refresh()
            ->load('role')
            ->loadCount([
                'carts',
                'orders',
                'payments',
            ]);

        return ApiResponse::success(
            data:
                new AdminUserResource(
                    $user,
                ),

            message:
                'Rol actualizado correctamente.',
        );
    }

    public function updateStatus(
        UpdateAdminUserStatusRequest $request,
        User $user,
    ): JsonResponse {
        $authenticatedUser =
            $request->user();

        $newStatus =
            $request->boolean(
                'is_active',
            );

        if (
            (int) $authenticatedUser->id ===
            (int) $user->id &&
            ! $newStatus
        ) {
            return ApiResponse::error(
                message:
                    'No puedes bloquear tu propia cuenta.',
                status:
                    Response::HTTP_CONFLICT,
                code:
                    'CANNOT_DISABLE_OWN_ACCOUNT',
            );
        }

        DB::transaction(
            function () use (
                $user,
                $newStatus,
            ): void {
                $lockedUser = User::query()
                    ->with('role')
                    ->lockForUpdate()
                    ->findOrFail(
                        $user->id,
                    );

                $isAdmin =
                    strtolower(
                        (string) $lockedUser
                            ->role
                            ?->role_name,
                    ) === 'admin';

                if (
                    $isAdmin &&
                    ! $newStatus &&
                    $lockedUser->is_active
                ) {
                    $activeAdmins =
                        User::query()
                            ->where(
                                'is_active',
                                true,
                            )
                            ->whereHas(
                                'role',
                                fn (
                                    Builder $query,
                                ): Builder =>
                                    $query->where(
                                        'role_name',
                                        'admin',
                                    ),
                            )
                            ->lockForUpdate()
                            ->count();

                    if ($activeAdmins <= 1) {
                        abort(
                            Response::HTTP_CONFLICT,
                            'No puedes bloquear al último administrador activo.',
                        );
                    }
                }

                $lockedUser->forceFill([
                    'is_active' =>
                        $newStatus,
                ])->save();

                if (! $newStatus) {
                    $lockedUser
                        ->tokens()
                        ->delete();
                }
            },
            attempts: 3,
        );

        $user
            ->refresh()
            ->load('role')
            ->loadCount([
                'carts',
                'orders',
                'payments',
            ]);

        return ApiResponse::success(
            data:
                new AdminUserResource(
                    $user,
                ),

            message: $newStatus
                ? 'Usuario activado correctamente.'
                : 'Usuario bloqueado correctamente.',
        );
    }
}
