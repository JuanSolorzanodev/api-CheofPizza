<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\StoreAdminUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserRoleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserStatusRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Admin\Users\AdminUserQueryService;
use App\Services\Admin\Users\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UserController
{
    public function __construct(
        private readonly AdminUserQueryService $queryService,
        private readonly AdminUserService $userService,
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
        $user = $this->userService->create(
            $request->validated(),
        );

        if ($user === null) {
            return ApiResponse::error(
                message: 'El rol seleccionado no existe.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'ROLE_NOT_FOUND',
            );
        }

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
        $user = $this->userService
            ->loadRelations($user);

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
        $user = $this->userService->update(
            user: $user,
            data: $request->validated(),
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

        $updatedUser = $this->userService->changeRole(
            user: $user,
            roleName: $request
                ->string('role')
                ->toString(),
        );

        if ($updatedUser === null) {
            return ApiResponse::error(
                message: 'El rol seleccionado no existe.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'ROLE_NOT_FOUND',
            );
        }

        return ApiResponse::success(
            data: new AdminUserResource(
                $updatedUser,
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

        $updatedUser = $this->userService
            ->changeStatus(
                user: $user,
                isActive: $newStatus,
            );

        return ApiResponse::success(
            data: new AdminUserResource(
                $updatedUser,
            ),
            message: $newStatus
                ? 'Usuario activado correctamente.'
                : 'Usuario bloqueado correctamente.',
        );
    }
}
