<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StorePaymentReceiptRequest;
use App\Http\Resources\Api\V1\PaymentReceiptResource;
use App\Models\User;
use App\Services\Payments\PaymentReceiptService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentReceiptController
{
    public function __construct(
        private readonly PaymentReceiptService $service,
    ) {
    }

    /**
     * Guarda un comprobante para un pedido del cliente.
     */
    public function store(
        StorePaymentReceiptRequest $request,
        int $orderId,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        abort_if(
            $user === null,
            401,
            'No autenticado.',
        );

        $file = $request->file('receipt');

        abort_if(
            $file === null,
            422,
            'Debes seleccionar un comprobante.',
        );

        $receipt = $this->service->storeForCustomer(
            orderId: $orderId,
            user: $user,
            file: $file,
        );

        return (
            new PaymentReceiptResource($receipt)
        )
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Devuelve el comprobante más reciente del pedido.
     */
    public function latest(
        Request $request,
        int $orderId,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();

        abort_if(
            $user === null,
            401,
            'No autenticado.',
        );

        $receipt = $this->service->latestForCustomer(
            orderId: $orderId,
            user: $user,
        );

        return response()->json([
            'data' => $receipt === null
                ? null
                : new PaymentReceiptResource($receipt),
        ]);
    }

    /**
     * Devuelve el historial de comprobantes del pedido.
     */
    public function index(
        Request $request,
        int $orderId,
    ): AnonymousResourceCollection {
        /** @var User|null $user */
        $user = $request->user();

        abort_if(
            $user === null,
            401,
            'No autenticado.',
        );

        $receipts = $this->service->historyForCustomer(
            orderId: $orderId,
            user: $user,
        );

        return PaymentReceiptResource::collection(
            $receipts,
        );
    }

    /**
     * Entrega un comprobante privado únicamente a usuarios autorizados.
     *
     * La ruta física del archivo nunca se expone al cliente.
     */
    public function file(
        Request $request,
        string $receiptUuid,
    ): StreamedResponse {
        /** @var User|null $user */
        $user = $request->user();

        abort_if(
            $user === null,
            401,
            'No autenticado.',
        );

        $user->loadMissing(
            'role:id,role_name',
        );

        $receipt = $this->service->findVisibleFile(
            receiptUuid: $receiptUuid,
            user: $user,
        );

        /**
         * El tipo explícito evita falsos positivos de Intelephense
         * para exists(), mimeType() y readStream().
         *
         * @var FilesystemAdapter $disk
         */
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

        $mimeType = trim(
            (string) $receipt->mime_type,
        );

        /*
         * Normalmente el MIME ya está guardado en la base de datos.
         * Este bloque funciona únicamente como respaldo.
         */
        if ($mimeType === '') {
            $detectedMimeType = $disk->mimeType(
                $path,
            );

            $mimeType = is_string($detectedMimeType)
                && $detectedMimeType !== ''
                    ? $detectedMimeType
                    : 'application/octet-stream';
        }

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
                'Content-Type' =>
                    $mimeType,

                'Content-Disposition' =>
                    sprintf(
                        'inline; filename="%s"',
                        $originalName,
                    ),

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate, max-age=0',

                'Pragma' =>
                    'no-cache',

                'Expires' =>
                    '0',

                'X-Content-Type-Options' =>
                    'nosniff',

                'X-Frame-Options' =>
                    'SAMEORIGIN',

                'Content-Security-Policy' =>
                    "default-src 'none'; frame-ancestors 'self'",
            ],
        );
    }

    /**
     * Elimina caracteres que podrían alterar el header
     * Content-Disposition.
     */
    private function sanitizeDownloadName(
        string $name,
    ): string {
        $name = str_replace(
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

        return $name !== ''
            ? $name
            : 'comprobante';
    }
}
