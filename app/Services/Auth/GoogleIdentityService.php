<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidGoogleIdentityException;
use App\Services\Auth\Data\GoogleIdentity;
use Illuminate\Support\Str;

final class GoogleIdentityService
{
    public function verify(
        string $idToken,
    ): GoogleIdentity {
        $verifiedToken = app(
            'firebase.auth',
        )->verifyIdToken(
            $idToken,
        );

        $claims = $verifiedToken->claims();

        $email = Str::lower(
            trim(
                (string) (
                    $claims->get('email')
                    ?? ''
                ),
            ),
        );

        if (
            $email === ''
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            throw InvalidGoogleIdentityException::missingEmail();
        }

        $firebaseUid = trim(
            (string) (
                $claims->get('sub')
                ?? ''
            ),
        );

        if ($firebaseUid === '') {
            throw InvalidGoogleIdentityException::missingUid();
        }

        [
            $firstName,
            $lastName,
        ] = $this->splitName(
            trim(
                (string) (
                    $claims->get('name')
                    ?? ''
                ),
            ),
        );

        return new GoogleIdentity(
            email: $email,
            firebaseUid: $firebaseUid,
            firstName: $firstName,
            lastName: $lastName,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(
        string $fullName,
    ): array {
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

        $lastName = count($parts) > 1
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
