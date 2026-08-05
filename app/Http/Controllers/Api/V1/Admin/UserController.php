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
use App\Services\Admin\Users\ActiveAdminGuard;
use App\Services\Admin\Users\AdminUserQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class UserController
{
    public function __construct(
        private readonly ActiveAdminGuard $activeAdminGuard,
        private readonly AdminUserQueryService $queryService,
    ) {}

    public function index(
        Request $request,
    ): JsonResponse {
        $users = $this->queryService->paginate(
            filters: $request->query(),
        );

        return ApiResponse::success(
            data: AdminUserResource::collection(
                $users->getCollection(),
            ),
            message: 'Usuarios recuperados correctamente.',
            meta: [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
        );
    }

    public function roles(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->queryService->roles(),
            message: 'Roles recuperados correctamente.',
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
                message: 'El rol seleccionado no existe.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'ROLE_NOT_FOUND',
            );
        }

        $user = User::query()->create([
            'role_id' => (int) $roleId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],

            /*
             * La contraseña aleatoria evita que el administrador conozca
             * credenciales reutilizables del usuario creado.
             */
            'password' => Str::random(64),
            'is_active' => (bool) $data['is_active'],
        ]);

        $this->loadUserRelations(
            $user,
        );

        return ApiResponse::success(
            data: new AdminUserResource(
                $user,
            ),
            message: 'Usuario creado correctamente.',
            status: Response::HTTP_CREATED,
        );
    }

    public function show(
        User $user,
    ): JsonResponse {
        $this->loadUserRelations(
            $user,
        );

        return ApiResponse::success(
            data: new AdminUserResource(
                $user,
            ),
            message: 'Usuario recuperado correctamente.',
        );
    }

    public function update(
        UpdateAdminUserRequest $request,
        User $user,
    ): JsonResponse {
        $user
            ->fill(
                $request->validated(),
            )
            ->save();

        $this->loadUserRelations(
            $user,
        );

        return ApiResponse::success(
            data: new AdminUserResource(
                $user,
            ),
            message: 'Usuario actualizado correctamente.',
        );
    }

    public function updateRole(
        UpdateAdminUserRoleRequest $request,
        User $user,
    ): JsonResponse {
        $authenticatedUser = $request->user();

        if (
            (int) $authenticatedUser->id ===
            (int) $user->id
        ) {
            return ApiResponse::error(
                message: 'No puedes cambiar tu propio rol.',
                status: Response::HTTP_CONFLICT,
                code: 'CANNOT_CHANGE_OWN_ROLE',
            );
        }

        $newRoleName = $request
            ->string('role')
            ->toString();

        $newRole = Role::query()
            ->where(
                'role_name',
                $newRoleName,
            )
            ->first();

        if ($newRole === null) {
            return ApiResponse::error(
                message: 'El rol seleccionado no existe.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'ROLE_NOT_FOUND',
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

                /*
                 * El cambio de privilegios invalida todas las sesiones
                 * existentes para evitar tokens con permisos anteriores.
                 */
                $lockedUser
                    ->tokens()
                    ->delete();
            },
            attempts: 3,
        );

        $user->refresh();

        $this->loadUserRelations(
            $user,
        );

        return ApiResponse::success(
            data: new AdminUserResource(
                $user,
            ),
            message: 'Rol actualizado correctamente.',
        );
    }

    public function updateStatus(
        UpdateAdminUserStatusRequest $request,
        User $user,
    ): JsonResponse {
        $authenticatedUser = $request->user();

        $newStatus = $request->boolean(
            'is_active',
        );

        if (
            (int) $authenticatedUser->id ===
            (int) $user->id
            && ! $newStatus
        ) {
            return ApiResponse::error(
                message: 'No puedes bloquear tu propia cuenta.',
                status: Response::HTTP_CONFLICT,
                code: 'CANNOT_DISABLE_OWN_ACCOUNT',
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

                $this->activeAdminGuard
                    ->ensureCanBeDisabled(
                        user: $lockedUser,
                        newStatus: $newStatus,
                    );

                $lockedUser
                    ->forceFill([
                        'is_active' => $newStatus,
                    ])
                    ->save();

                if (! $newStatus) {
                    /*
                     * Un usuario bloqueado no debe conservar sesiones activas.
                     */
                    $lockedUser
                        ->tokens()
                        ->delete();
                }
            },
            attempts: 3,
        );

        $user->refresh();

        $this->loadUserRelations(
            $user,
        );

        return ApiResponse::success(
            data: new AdminUserResource(
                $user,
            ),
            message: $newStatus
                ? 'Usuario activado correctamente.'
                : 'Usuario bloqueado correctamente.',
        );
    }

    /**
     * Carga las relaciones y contadores requeridos por AdminUserResource.
     */
    private function loadUserRelations(
        User $user,
    ): void {
        $user
            ->load('role')
            ->loadCount([
                'carts',
                'orders',
                'payments',
            ]);
    }
}
