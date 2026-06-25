<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Role;
use App\Services\Cart\CartService;
use Illuminate\Support\Str;
use Throwable;

class AuthService
{
    public function __construct(
        private CartService $cartService
    ) {}

    public function loginWithGoogle(array $data, ?string $sessionId): array
    {
        try {
            $claims = $this->verifyGoogleToken($data['id_token']);

            if (empty($claims['email'])) {
                throw new \Exception(
                    'Token válido pero sin email.'
                );
            }

            $user = $this->findOrCreateUser($claims, $data['phone'] ?? null);

            $token = $user->createToken('google')->plainTextToken;

            $cart = $this->cartService->getOrCreateActiveCart($user->id, $sessionId);

            return [
                'data' => [
                    'token' => $token,
                    'user' => $user,
                    'cart' => $cart
                ],
                'cart_session' => $cart->session_id
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    private function verifyGoogleToken(string $token): array
    {
        $firebaseAuth = app('firebase.auth');

        $verified = $firebaseAuth->verifyIdToken($token);

        $claims = $verified->claims();

        return [
            'email' => (string) ($claims->get('email') ?? ''),
            'name' => (string) ($claims->get('name') ?? ''),
        ];
    }

    private function findOrCreateUser(array $data, ?string $phone): User
    {

        $email = $data['email'];
        [$firstName, $lastName] = $this->splitName($data['name']);

        $user = User::where('email', $email)->first();

        if (!$user && empty($phone)) {
            abort(
                response()->json([
                    'message' => 'Se requiere phone para completar el registro.',
                    'code' => 'PHONE_REQUIRED'
                ], 422)
            );
        }

        if (!$user) {
            $newUser = User::create([
                'email' => $email,
                'role_id' => $this->getCustomerRoleId(),
                'first_name' => $firstName !== '' ? $firstName : 'Cliente',
                'last_name' => $lastName !== '' ? $lastName : 'Google',
                'phone' => $phone,
                'password' => Str::random(40),
            ]);
            return $newUser;
        }

        $user->update([
            'first_name' => $firstName ?: $user->first_name,
            'last_name' => $lastName ?: $user->last_name,
        ]);
        return $user;
    }

    private function getCustomerRoleId(): int
    {
        $customerRoleId = Role::where('role_name', 'customer')->value('id');

        return $customerRoleId;
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));

        $count = count($parts);

        return match (true) {
            $count === 0 => ['', ''],

            $count === 1 => [$parts[0], ''],

            $count === 2 => [$parts[0], $parts[1]],

            $count === 3 => [
                implode(' ', array_slice($parts, 0, 2)),
                $parts[2]
            ],

            default => [
                implode(' ', array_slice($parts, 0, -2)),
                implode(' ', array_slice($parts, -2))
            ],
        };
    }
}
