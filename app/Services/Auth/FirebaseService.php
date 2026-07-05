<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidGoogleTokenException;

class FirebaseService
{
    public function verifyGoogleToken(string $token): array
    {
        $firebase = app('firebase.auth');

        $verified = $firebase->verifyIdToken($token);

        $claims = $verified->claims();

        if (empty($claims->get('email'))) {
            throw new InvalidGoogleTokenException();
        }

        return [
            'email' => (string) $claims->get('email'),
            'name' => (string) $claims->get('name'),
        ];
    }
}
