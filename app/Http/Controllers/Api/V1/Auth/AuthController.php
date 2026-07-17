<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\FirebaseGoogleLoginRequest;
use App\Http\Resources\Api\V1\AuthUserResource;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Throwable;

final class AuthController
{
    public function loginWithGoogle(
        FirebaseGoogleLoginRequest $request,
        CartService $cartService,
    ): JsonResponse {
        try {
            $verifiedToken = app('firebase.auth')->verifyIdToken(
                $request->string('id_token')->toString()
            );

            $claims = $verifiedToken->claims();

            $email = Str::lower(
                trim((string) ($claims->get('email') ?? ''))
            );

            $firebaseUid = trim(
                (string) ($claims->get('sub') ?? '')
            );

            $displayName = trim(
                (string) ($claims->get('name') ?? '')
            );

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ApiResponse::error(
                    message: 'La cuenta de Google no proporcionó un correo válido.',
                    status: 422,
                    code: 'GOOGLE_EMAIL_REQUIRED',
                );
            }

            if ($firebaseUid === '') {
                return ApiResponse::error(
                    message: 'No fue posible identificar la cuenta de Google.',
                    status: 422,
                    code: 'GOOGLE_UID_REQUIRED',
                );
            }

            [$firstName, $lastName] = $this->splitName($displayName);

            $customerRoleId = Role::query()
                ->where('role_name', 'customer')
                ->value('id');

            if ($customerRoleId === null) {
                Log::critical(
                    'No existe el rol customer requerido para autenticación.'
                );

                return ApiResponse::error(
                    message: 'La autenticación no está configurada correctamente.',
                    status: 500,
                    code: 'AUTH_ROLE_NOT_CONFIGURED',
                );
            }

            $sessionId = $request->header('X-Cart-Session')
                ?? $request->input('cart_session_id');

            $result = DB::transaction(
                function () use (
                    $email,
                    $firstName,
                    $lastName,
                    $customerRoleId,
                    $request,
                    $cartService,
                    $sessionId,
                ): array {
                    $user = User::query()
                        ->where('email', $email)
                        ->lockForUpdate()
                        ->first();

                    if ($user === null && !$request->filled('phone')) {
                        return [
                            'error' => ApiResponse::error(
                                message: 'Ingresa tu número de teléfono para completar el registro.',
                                status: 422,
                                code: 'PHONE_REQUIRED',
                                errors: [
                                    'phone' => [
                                        'El número de teléfono es obligatorio para clientes nuevos.',
                                    ],
                                ],
                            ),
                        ];
                    }

                    if ($user === null) {
                        $user = User::query()->create([
                            'email' => $email,
                            'role_id' => (int) $customerRoleId,
                            'first_name' => $firstName !== ''
                                ? $firstName
                                : 'Cliente',
                            'last_name' => $lastName !== ''
                                ? $lastName
                                : 'Google',
                            'phone' => $request->string('phone')->toString(),
                            'password' => Str::random(64),
                        ]);
                    } else {
                        $updates = [];

                        if ($firstName !== '') {
                            $updates['first_name'] = $firstName;
                        }

                        if ($lastName !== '') {
                            $updates['last_name'] = $lastName;
                        }

                        if ($updates !== []) {
                            $user->fill($updates)->save();
                        }
                    }

                    /*
                     * Permitimos varias sesiones/dispositivos. Más adelante
                     * podremos añadir administración y expiración de sesiones.
                     */
                    $plainTextToken = $user
                        ->createToken('google-web')
                        ->plainTextToken;

                    $cart = $cartService->getOrCreateActiveCart(
                        userId: (int) $user->id,
                        sessionId: is_string($sessionId)
                            ? $sessionId
                            : null,
                    );

                    $user->load('role');

                    return [
                        'user' => $user,
                        'token' => $plainTextToken,
                        'cart' => $cart,
                    ];
                },
                attempts: 3,
            );

            if (isset($result['error'])) {
                return $result['error'];
            }

            return ApiResponse::success(
                data: [
                    'token' => $result['token'],
                    'user' => new AuthUserResource($result['user']),
                    'cart' => new CartResource($result['cart']),
                ],
                message: 'Sesión iniciada correctamente.',
            )->header(
                'X-Cart-Session',
                (string) $result['cart']->session_id,
            );
        } catch (FailedToVerifyToken $exception) {
            Log::notice('Token de Firebase rechazado.', [
                'exception' => $exception::class,
            ]);

            return ApiResponse::error(
                message: 'La sesión de Google no es válida o ha expirado.',
                status: 401,
                code: 'INVALID_FIREBASE_TOKEN',
            );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                message: 'No fue posible iniciar sesión en este momento.',
                status: 500,
                code: 'AUTHENTICATION_FAILED',
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];

        $firstName = trim((string) ($parts[0] ?? ''));
        $lastName = count($parts) > 1
            ? trim(implode(' ', array_slice($parts, 1)))
            : '';

        return [$firstName, $lastName];
    }
}
