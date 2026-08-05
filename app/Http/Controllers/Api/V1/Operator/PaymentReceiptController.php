<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operator;

use App\Http\Requests\Api\V1\Operator\PaymentReceiptIndexRequest;
use App\Http\Requests\Api\V1\Operator\RejectPaymentReceiptRequest;
use App\Http\Resources\Api\V1\PaymentReceiptResource;
use App\Models\User;
use App\Services\Payments\PaymentReceiptService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PaymentReceiptController
{
    public function __construct(
        private readonly PaymentReceiptService $service,
    ) {}

    public function index(
        PaymentReceiptIndexRequest $request,
    ): AnonymousResourceCollection {
        return PaymentReceiptResource::collection(
            $this->service->paginatePending(
                perPage: $request->perPage(),
            ),
        );
    }

    public function approve(
        Request $request,
        string $receiptUuid,
    ): PaymentReceiptResource {
        /** @var User $reviewer */
        $reviewer = $request->user();

        return new PaymentReceiptResource(
            $this->service->approve(
                receiptUuid: $receiptUuid,
                reviewer: $reviewer,
            ),
        );
    }

    public function reject(
        RejectPaymentReceiptRequest $request,
        string $receiptUuid,
    ): PaymentReceiptResource {
        /** @var User $reviewer */
        $reviewer = $request->user();

        return new PaymentReceiptResource(
            $this->service->reject(
                receiptUuid: $receiptUuid,
                reviewer: $reviewer,
                reason: $request->reason(),
            ),
        );
    }
}
