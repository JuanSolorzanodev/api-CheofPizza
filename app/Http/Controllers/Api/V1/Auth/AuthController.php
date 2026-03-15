<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\FirebaseGoogleLoginRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Support\Str;
use Throwable;

class AuthController
{
    public function loginWithGoogle(FirebaseGoogleLoginRequest $request, CartService $cartService)
    {
        try {
            $firebaseAuth = app('firebase.auth');

            $verified = $firebaseAuth->verifyIdToken($request->string('id_token')->toString());
            $claims = $verified->claims();

            $email = (string) ($claims->get('email') ?? '');
            $name  = (string) ($claims->get('name') ?? '');

            if ($email === '') {
                return response()->json(['message' => 'Token válido pero sin email.'], 422);
            }

            [$firstName, $lastName] = $this->splitName($name);

            // 1) Buscar usuario existente
            $user = User::where('email', $email)->first();

            // 2) Si NO existe, obligar phone para completar registro (como ya lo haces)
            if (!$user && !$request->filled('phone')) {
                return response()->json([
                    'message' => 'Se requiere phone para completar el registro.',
                    'code' => 'PHONE_REQUIRED'
                ], 422);
            }

            // 3) Rol customer debe existir por seeder (no lo crees en runtime)
            $customerRoleId = Role::where('role_name', 'customer')->value('id');
            if (!$customerRoleId) {
                return response()->json([
                    'message' => 'Configuración inválida: el rol "customer" no existe. Ejecuta RoleSeeder.',
                    'code' => 'ROLE_NOT_CONFIGURED'
                ], 500);
            }

            // 4) Crear o actualizar SIN pisar role_id / phone / password
            if ($user) {
                // Actualiza solo datos no críticos (y sin borrar datos existentes)
                $user->update([
                    'first_name' => $firstName !== '' ? $firstName : $user->first_name,
                    'last_name'  => $lastName !== '' ? $lastName : $user->last_name,
                    // NO tocar: role_id, phone, password
                ]);
            } else {
                $user = User::create([
                    'email'      => $email,
                    'role_id'    => $customerRoleId,
                    'first_name' => $firstName !== '' ? $firstName : 'Cliente',
                    'last_name'  => $lastName !== '' ? $lastName : 'Google',
                    'phone'      => $request->input('phone'),
                    // solo en create
                    'password'   => Str::random(40),
                ]);
            }

            // 5) Token
            $token = $user->createToken('google')->plainTextToken;

            // 6) Sesión del carrito (header o body)
            $sessionId = $request->header('X-Cart-Session')
                ?? $request->input('cart_session_id');

            // 7) Claim/merge carrito
            $cart = $cartService->getOrCreateActiveCart($user->id, $sessionId);

            return response()->json([
                'token' => $token,
                'user'  => $user,
                'cart'  => new CartResource($cart),
            ])->header('X-Cart-Session', $cart->session_id);

        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Token inválido o no verificable.',
            ], 401);
        }
    }

    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') return ['', ''];

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $first = $parts[0] ?? '';
        $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        return [$first, $last];
    }
}
