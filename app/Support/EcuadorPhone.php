<?php

declare(strict_types=1);

namespace App\Support;

final class EcuadorPhone
{
    /**
     * Convierte un teléfono móvil ecuatoriano a formato internacional:
     *
     * 0991234567     → +593991234567
     * 991234567      → +593991234567
     * 593991234567   → +593991234567
     * +593991234567  → +593991234567
     */
    public static function normalize(
        ?string $value,
    ): string {
        $digits = preg_replace(
            '/\D+/',
            '',
            (string) $value,
        ) ?? '';

        if ($digits === '') {
            return '';
        }

        if (
            str_starts_with(
                $digits,
                '593',
            )
        ) {
            $digits = substr(
                $digits,
                3,
            );
        }

        if (
            str_starts_with(
                $digits,
                '0',
            )
        ) {
            $digits = substr(
                $digits,
                1,
            );
        }

        if (
            ! preg_match(
                '/^9\d{8}$/',
                $digits,
            )
        ) {
            return '';
        }

        return '+593'.$digits;
    }

    public static function isValid(
        ?string $value,
    ): bool {
        return self::normalize(
            $value,
        ) !== '';
    }

    private function __construct() {}
}
