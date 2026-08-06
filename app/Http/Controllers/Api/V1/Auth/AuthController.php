<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\Auth\InvalidGoogleIdentityException;
use App\Http\Requests\Api\V1\Auth\FirebaseGoogleLoginRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\CustomerAccountService;
use App\Services\Auth\GoogleIdentityService;
use App\Services\Auth\PasswordAuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

final class AuthController
{
    public function register(
        RegisterRequest $request,
        AuthSessionService $sessionService,
        CustomerAccountService $accountService,
    ): JsonResponse {
        $customerRoleId = $accountService
            ->customerRoleId();

        if ($customerRoleId === null) {
            return $this->roleNotConfiguredResponse();
        }

        $user = $accountService
            ->registerWithPassword(
                customerRoleId: $customerRoleId,

                data: [
                    'first_name' => $request
                        ->string('first_name')
                        ->toString(),

                    'last_name' => $request
                        ->string('last_name')
                        ->toString(),

                    'phone' => $request
                        ->string('phone')
                        ->toString(),

                    'email' => $request
                        ->string('email')
                        ->toString(),

                    'password' => $request
                        ->string('password')
                        ->toString(),
                ],
            );

        $cartSessionId = $sessionService
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
                tokenName: 'password-web',
                cartSessionId: $cartSessionId,
                message: 'Tu cuenta fue creada correctamente.',
                status: 201,
            );
    }

    public function login(
        LoginRequest $request,
        AuthSessionService $sessionService,
        PasswordAuthenticationService $authenticationService,
    ): JsonResponse {
        $email = $request
            ->string('email')
            ->toString();

        $password = $request
            ->string('password')
            ->toString();

        $user = $authenticationService
            ->findByEmail(
                $email,
            );

        /*
     * Se utiliza un mensaje genérico para no revelar
     * si el correo existe en la plataforma.
     */
        if (
            ! $authenticationService
                ->credentialsAreValid(
                    user: $user,
                    password: $password,
                )
        ) {
            return ApiResponse::error(
                message: 'El correo o la contraseña no son correctos.',
                status: 422,
                code: 'INVALID_CREDENTIALS',
                errors: [
                    'email' => [
                        'El correo o la contraseña no son correctos.',
                    ],
                ],
            );
        }

        if (! $user->is_active) {
            return ApiResponse::error(
                message: 'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',
                status: 403,
                code: 'USER_INACTIVE',
            );
        }

        $cartSessionId = $sessionService
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
                tokenName: 'password-web',
                cartSessionId: $cartSessionId,
            );
    }

    public function loginWithGoogle(
        FirebaseGoogleLoginRequest $request,
        AuthSessionService $sessionService,
        CustomerAccountService $accountService,
        GoogleIdentityService $googleIdentityService,
    ): JsonResponse {
        try {
            $identity = $googleIdentityService
                ->verify(
                    $request
                        ->string('id_token')
                        ->toString(),
                );

            $customerRoleId = $accountService
                ->customerRoleId();

            if ($customerRoleId === null) {
                return $this
                    ->roleNotConfiguredResponse();
            }

            $existingUser = $accountService
                ->findByEmail(
                    $identity->email,
                );

            /*
         * Un usuario nuevo de Google debe completar sus datos
         * antes de crear una cuenta persistente.
         */
            if (
                $existingUser === null
                && ! $request->filled('phone')
            ) {
                return ApiResponse::error(
                    message: 'Completa tus datos para crear tu cuenta.',
                    status: 422,
                    code: 'PROFILE_COMPLETION_REQUIRED',
                    errors: [
                        'phone' => [
                            'Ingresa un número de teléfono para coordinar tus pedidos.',
                        ],
                    ],
                );
            }

            $firstName = $request->filled('first_name')
                ? $request
                    ->string('first_name')
                    ->toString()
                : $identity->firstName;

            $lastName = $request->filled('last_name')
                ? $request
                    ->string('last_name')
                    ->toString()
                : $identity->lastName;

            $user = $accountService
                ->findOrCreateFromGoogle(
                    customerRoleId: $customerRoleId,
                    email: $identity->email,
                    firstName: $firstName,
                    lastName: $lastName,

                    phone: $request
                        ->string('phone')
                        ->toString(),
                );

            if (! $user->is_active) {
                return ApiResponse::error(
                    message: 'Tu cuenta se encuentra bloqueada. Comunícate con el administrador.',
                    status: 403,
                    code: 'USER_INACTIVE',
                );
            }

            $cartSessionId = $sessionService
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
                    tokenName: 'google-web',
                    cartSessionId: $cartSessionId,
                );
        } catch (InvalidGoogleIdentityException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                status: 422,
                code: $exception->errorCode,
            );
        } catch (FailedToVerifyToken $exception) {
            Log::notice(
                'Token de Firebase rechazado.',
                [
                    'exception' => $exception::class,
                ],
            );

            return ApiResponse::error(
                message: 'La sesión de Google no es válida o ha expirado.',
                status: 401,
                code: 'INVALID_FIREBASE_TOKEN',
            );
        }
    }

    private function roleNotConfiguredResponse(): JsonResponse
    {
        Log::critical(
            'No existe el rol customer requerido para autenticación.',
        );

        return ApiResponse::error(
            message: 'La autenticación no está configurada correctamente.',

            status: 500,

            code: 'AUTH_ROLE_NOT_CONFIGURED',
        );
    }
}
