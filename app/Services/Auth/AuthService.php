<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginResponse;
use App\Enums\UserRole;
use App\Exceptions\PhoneRequiredException;
use App\Models\User;
use App\Models\Role;
use App\Services\Cart\CartService;
use Illuminate\Support\Str;
use Throwable;

class AuthService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly FirebaseService $firebaseService
    ) {}

    public function loginWithGoogle(array $data, ?string $sessionId): LoginResponse
    {
        $claims = $this->firebaseService->verifyGoogleToken($data['id_token']);

        $user = $this->findOrCreateUser($claims, $data['phone'] ?? null);

        $token = $user->createToken('google')->plainTextToken;

        $cart = $this->cartService->getOrCreateActiveCart($user->id, $sessionId);

        return new LoginResponse(
            token: $token,
            user: $user,
            cart: $cart,
            cartSession: $cart->session_id
        );
    }

    public function findOrCreateUser(array $data, ?string $phone): User
    {
        $email = $data['email'];

        [$firstName, $lastName] = $this->splitName($data['name']);

        $user = User::where('email', $email)->first();

        if (!$user && empty($phone)) {
            throw new PhoneRequiredException();
        }

        if (!$user) {
            $newUser = User::create([
                'role_id' => UserRole::CUSTOMER->value,
                'phone' => $phone,
                'first_name' => $firstName !== '' ? $firstName : 'Cliente',
                'last_name' => $lastName !== '' ? $lastName : 'Google',
                'email' => $email,
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

    public function splitName(string $fullName): array
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
