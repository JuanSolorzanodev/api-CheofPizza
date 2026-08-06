<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Requests\Api\V1\Orders\StorePaymentReceiptRequest;
use App\Http\Resources\Api\V1\PaymentReceiptResource;
use App\Models\User;
use App\Services\Payments\PaymentReceiptFileService;
use App\Services\Payments\PaymentReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentReceiptController
{
    public function __construct(
        private readonly PaymentReceiptService $service,
    ) {}

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
        PaymentReceiptFileService $fileService,
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

        $receipt = $this->service
            ->findVisibleFile(
                receiptUuid: $receiptUuid,
                user: $user,
            );

        return $fileService->stream(
            $receipt,
        );
    }
}
