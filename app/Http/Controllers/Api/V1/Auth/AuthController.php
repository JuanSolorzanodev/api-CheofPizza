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

            $existing = User::where('email', $email)->first();

            if (!$existing && !$request->filled('phone')) {
                return response()->json([
                    'message' => 'Se requiere phone para completar el registro.',
                    'code' => 'PHONE_REQUIRED'
                ], 422);
            }

            $customerRoleId = Role::firstOrCreate(['role_name' => 'customer'])->id;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'role_id'    => $customerRoleId,
                    'first_name' => $firstName !== '' ? $firstName : 'Cliente',
                    'last_name'  => $lastName !== '' ? $lastName : 'Google',
                    'phone'      => $existing?->phone ?? $request->input('phone'),
                    'password'   => Str::random(40),
                ]
            );

            $token = $user->createToken('google')->plainTextToken;

            // Tomamos sesión del carrito desde header o body
            $sessionId = $request->header('X-Cart-Session')
                ?? $request->input('cart_session_id');

            // Esto hace claim/merge gracias al CartService modificado
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
