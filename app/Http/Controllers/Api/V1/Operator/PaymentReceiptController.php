<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operator;

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
        Request $request,
    ): AnonymousResourceCollection {
        return PaymentReceiptResource::collection(
            $this->service
                ->paginatePending(
                    (int) $request->integer(
                        'per_page',
                        15,
                    ),
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
                $receiptUuid,
                $reviewer,
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
