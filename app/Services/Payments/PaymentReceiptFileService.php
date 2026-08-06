<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\PaymentReceipt;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentReceiptFileService
{
    public function stream(
        PaymentReceipt $receipt,
    ): StreamedResponse {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(
            (string) $receipt->disk,
        );

        $path = trim(
            (string) $receipt->file_path,
        );

        abort_if(
            $path === '',
            404,
            'El comprobante no tiene un archivo asociado.',
        );

        abort_unless(
            $disk->exists($path),
            404,
            'El archivo del comprobante no existe.',
        );

        $mimeType = $this->resolveMimeType(
            disk: $disk,
            path: $path,
            storedMimeType: (string) $receipt->mime_type,
        );

        $originalName = $this->sanitizeDownloadName(
            (string) $receipt->original_name,
        );

        return response()->stream(
            function () use (
                $disk,
                $path,
            ): void {
                $stream = $disk->readStream(
                    $path,
                );

                if (! is_resource($stream)) {
                    throw new RuntimeException(
                        'No fue posible abrir el comprobante.',
                    );
                }

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,

                'Content-Disposition' => sprintf(
                    'inline; filename="%s"',
                    $originalName,
                ),

                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',

                'Pragma' => 'no-cache',

                'Expires' => '0',

                'X-Content-Type-Options' => 'nosniff',

                /*
                 * Este recurso sí puede mostrarse dentro del visor
                 * del frontend perteneciente al mismo origen.
                 */
                'X-Frame-Options' => 'SAMEORIGIN',

                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
            ],
        );
    }

    private function resolveMimeType(
        FilesystemAdapter $disk,
        string $path,
        string $storedMimeType,
    ): string {
        $mimeType = trim(
            $storedMimeType,
        );

        if ($mimeType !== '') {
            return $mimeType;
        }

        $detectedMimeType = $disk->mimeType(
            $path,
        );

        return is_string($detectedMimeType)
            && $detectedMimeType !== ''
                ? $detectedMimeType
                : 'application/octet-stream';
    }

    private function sanitizeDownloadName(
        string $name,
    ): string {
        $sanitizedName = str_replace(
            [
                '"',
                "'",
                "\r",
                "\n",
                '\\',
                '/',
            ],
            '',
            trim($name),
        );

        return $sanitizedName !== ''
            ? $sanitizedName
            : 'comprobante';
    }
}
