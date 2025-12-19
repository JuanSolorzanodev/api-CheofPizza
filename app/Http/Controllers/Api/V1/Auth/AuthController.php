<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\FirebaseGoogleLoginRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

class AuthController 
{
    public function loginWithGoogle(FirebaseGoogleLoginRequest $request)
    {
        $firebaseAuth = app('firebase.auth'); // kreait/laravel-firebase

        $verified = $firebaseAuth->verifyIdToken($request->string('id_token')->toString());
        $claims = $verified->claims();

        $email = (string) ($claims->get('email') ?? '');
        $name  = (string) ($claims->get('name') ?? '');

        if ($email === '') {
            return response()->json(['message' => 'Token válido pero sin email.'], 422);
        }

        [$firstName, $lastName] = $this->splitName($name);

        $user = User::where('email', $email)->first();

        // Si es nuevo, tu tabla users exige phone NOT NULL
        if (!$user && !$request->filled('phone')) {
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
                'phone'      => $user?->phone ?? $request->input('phone'),
                'password'   => Str::random(40),
            ]
        );

        $token = $user->createToken('google')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
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
