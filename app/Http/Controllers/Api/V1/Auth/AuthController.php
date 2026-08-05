<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\FirebaseGoogleLoginRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Throwable;

final class AuthController
{
    public function register(
        RegisterRequest $request,
        AuthSessionService $sessionService,
    ): JsonResponse {
        try {
            $customerRoleId = $this->customerRoleId();

            if ($customerRoleId === null) {
                return $this->roleNotConfiguredResponse();
            }

            $user = DB::transaction(
                static function () use (
                    $request,
                    $customerRoleId,
                ): User {
                    return User::query()->create([
                        'role_id' =>
                            $customerRoleId,

                        'first_name' =>
                            $request
                                ->string(
                                    'first_name',
                                )
                                ->toString(),

                        'last_name' =>
                            $request
                                ->string(
                                    'last_name',
                                )
                                ->toString(),

                        'phone' =>
                            $request
                                ->string(
                                    'phone',
                                )
                                ->toString(),

                        'email' =>
                            $request
                                ->string(
                                    'email',
                                )
                                ->toString(),

                        /*
                         * El modelo User utiliza el cast "hashed".
                         * Laravel almacenará la contraseña de forma segura.
                         */
                        'password' =>
                            $request
                                ->string(
                                    'password',
                                )
                                ->toString(),

                        'is_active' =>
                            true,
                    ]);
                },
                attempts: 3,
            );

            $cartSessionId =
                $sessionService
                    ->resolveCartSessionId(
                        $request->header(
                            'X-Cart-Session',
                        ),

                        $request->input(
                            'cart_session_id',
                        ),
                    );

            return $sessionService
                ->createResponse(
                    user: $user,

                    tokenName:
                        'password-web',

                    cartSessionId:
                        $cartSessionId,

                    message:
                        'Tu cuenta fue creada correctamente.',

                    status: 201,
                );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                message:
                    'No fue posible crear tu cuenta en este momento.',

                status: 500,

                code:
                    'REGISTRATION_FAILED',
            );
        }
    }

    public function login(
        LoginRequest $request,
        AuthSessionService $sessionService,
    ): JsonResponse {
        try {
            $email = $request
                ->string(
                    'email',
                )
                ->toString();

            $user = User::query()
                ->where(
                    'email',
                    $email,
                )
                ->first();

            /*
             * Se utiliza un mensaje genérico para no revelar
             * si el correo existe en la plataforma.
             */
            if (
                $user === null
                || ! Hash::check(
                    $request
                        ->string(
                            'password',
                        )
                        ->toString(),

                    (string) $user->password,
                )
            ) {
                return ApiResponse::error(
                    message:
                        'El correo o la contraseña no son correctos.',

                    status: 422,

                    code:
                        'INVALID_CREDENTIALS',

                    errors: [
                        'email' => [
                            'El correo o la contraseña no son correctos.',
                        ],
                    ],
                );
            }

            if (! $user->is_active) {
                return ApiResponse::error(
                    message:
                        'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',

                    status: 403,

                    code:
                        'USER_INACTIVE',
                );
            }

            $cartSessionId =
                $sessionService
                    ->resolveCartSessionId(
                        $request->header(
                            'X-Cart-Session',
                        ),

                        $request->input(
                            'cart_session_id',
                        ),
                    );

            return $sessionService
                ->createResponse(
                    user: $user,

                    tokenName:
                        'password-web',

                    cartSessionId:
                        $cartSessionId,
                );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                message:
                    'No fue posible iniciar sesión en este momento.',

                status: 500,

                code:
                    'AUTHENTICATION_FAILED',
            );
        }
    }

    public function loginWithGoogle(
        FirebaseGoogleLoginRequest $request,
        AuthSessionService $sessionService,
    ): JsonResponse {
        try {
            $verifiedToken = app(
                'firebase.auth',
            )->verifyIdToken(
                $request
                    ->string(
                        'id_token',
                    )
                    ->toString(),
            );

            $claims =
                $verifiedToken->claims();

            $email = Str::lower(
                trim(
                    (string) (
                        $claims->get(
                            'email',
                        )
                        ?? ''
                    ),
                ),
            );

            $firebaseUid = trim(
                (string) (
                    $claims->get(
                        'sub',
                    )
                    ?? ''
                ),
            );

            $displayName = trim(
                (string) (
                    $claims->get(
                        'name',
                    )
                    ?? ''
                ),
            );

            if (
                $email === ''
                || ! filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL,
                )
            ) {
                return ApiResponse::error(
                    message:
                        'La cuenta de Google no proporcionó un correo válido.',

                    status: 422,

                    code:
                        'GOOGLE_EMAIL_REQUIRED',
                );
            }

            if ($firebaseUid === '') {
                return ApiResponse::error(
                    message:
                        'No fue posible identificar la cuenta de Google.',

                    status: 422,

                    code:
                        'GOOGLE_UID_REQUIRED',
                );
            }

            [
                $googleFirstName,
                $googleLastName,
            ] = $this->splitName(
                $displayName,
            );

            $customerRoleId =
                $this->customerRoleId();

            if ($customerRoleId === null) {
                return $this
                    ->roleNotConfiguredResponse();
            }

            $existingUser = User::query()
                ->where(
                    'email',
                    $email,
                )
                ->first();

            /*
             * Un usuario nuevo de Google debe completar sus datos
             * antes de crear una cuenta persistente.
             */
            if (
                $existingUser === null
                && ! $request->filled(
                    'phone',
                )
            ) {
                return ApiResponse::error(
                    message:
                        'Completa tus datos para crear tu cuenta.',

                    status: 422,

                    code:
                        'PROFILE_COMPLETION_REQUIRED',

                    errors: [
                        'phone' => [
                            'Ingresa un número de teléfono para coordinar tus pedidos.',
                        ],
                    ],
                );
            }

            $user = DB::transaction(
                function () use (
                    $request,
                    $email,
                    $googleFirstName,
                    $googleLastName,
                    $customerRoleId,
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

                    $firstName = $request
                        ->filled(
                            'first_name',
                        )
                            ? $request
                                ->string(
                                    'first_name',
                                )
                                ->toString()
                            : $googleFirstName;

                    $lastName = $request
                        ->filled(
                            'last_name',
                        )
                            ? $request
                                ->string(
                                    'last_name',
                                )
                                ->toString()
                            : $googleLastName;

                    return User::query()->create([
                        'email' =>
                            $email,

                        'role_id' =>
                            $customerRoleId,

                        'first_name' =>
                            $firstName !== ''
                                ? $firstName
                                : 'Cliente',

                        'last_name' =>
                            $lastName !== ''
                                ? $lastName
                                : 'Google',

                        'phone' =>
                            $request
                                ->string(
                                    'phone',
                                )
                                ->toString(),

                        /*
                         * La cuenta de Google no utiliza esta contraseña.
                         * Se genera una credencial imposible de conocer
                         * desde el cliente.
                         */
                        'password' =>
                            Str::password(
                                length: 64,
                            ),

                        'is_active' =>
                            true,
                    ]);
                },
                attempts: 3,
            );

            if (! $user->is_active) {
                return ApiResponse::error(
                    message:
                        'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',

                    status: 403,

                    code:
                        'USER_INACTIVE',
                );
            }

            $cartSessionId =
                $sessionService
                    ->resolveCartSessionId(
                        $request->header(
                            'X-Cart-Session',
                        ),

                        $request->input(
                            'cart_session_id',
                        ),
                    );

            return $sessionService
                ->createResponse(
                    user: $user,

                    tokenName:
                        'google-web',

                    cartSessionId:
                        $cartSessionId,
                );
        } catch (FailedToVerifyToken $exception) {
            Log::notice(
                'Token de Firebase rechazado.',
                [
                    'exception' =>
                        $exception::class,
                ],
            );

            return ApiResponse::error(
                message:
                    'La sesión de Google no es válida o ha expirado.',

                status: 401,

                code:
                    'INVALID_FIREBASE_TOKEN',
            );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                message:
                    'No fue posible iniciar sesión en este momento.',

                status: 500,

                code:
                    'AUTHENTICATION_FAILED',
            );
        }
    }

    private function customerRoleId(): int|null
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

    private function roleNotConfiguredResponse(): JsonResponse
    {
        Log::critical(
            'No existe el rol customer requerido para autenticación.',
        );

        return ApiResponse::error(
            message:
                'La autenticación no está configurada correctamente.',

            status: 500,

            code:
                'AUTH_ROLE_NOT_CONFIGURED',
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(
        string $fullName,
    ): array {
        $fullName = trim(
            $fullName,
        );

        if ($fullName === '') {
            return [
                '',
                '',
            ];
        }

        $parts = preg_split(
            '/\s+/',
            $fullName,
        ) ?: [];

        $firstName = trim(
            (string) (
                $parts[0]
                ?? ''
            ),
        );

        $lastName = count(
            $parts,
        ) > 1
            ? trim(
                implode(
                    ' ',
                    array_slice(
                        $parts,
                        1,
                    ),
                ),
            )
            : '';

        return [
            $firstName,
            $lastName,
        ];
    }
}
