<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentReceipt
 */
final class PaymentReceiptResource extends JsonResource
{
    public function toArray(
        Request $request,
    ): array {
        return [
            'id' =>
                (int) $this->id,

            'uuid' =>
                (string) $this->uuid,

            'order_id' =>
                (int) $this->order_id,

            'order' =>
                $this->whenLoaded(
                    'order',
                    fn (): ?array =>
                        $this->order === null
                            ? null
                            : [
                                'id' =>
                                    (int) $this->order->id,

                                'order_number' =>
                                    (string) $this->order
                                        ->order_number,

                                'total' =>
                                    (float) $this->order
                                        ->total,

                                'ordered_at' =>
                                    $this->order
                                        ->ordered_at
                                        ?->toISOString(),

                                'payment_method' =>
                                    $this->order
                                        ->paymentMethod
                                        ?->name,

                                'customer' =>
                                    $this->order->user === null
                                        ? null
                                        : [
                                            'id' =>
                                                (int) $this
                                                    ->order
                                                    ->user
                                                    ->id,

                                            'name' =>
                                                trim(
                                                    (string) $this
                                                        ->order
                                                        ->user
                                                        ->first_name
                                                    .' '.
                                                    (string) $this
                                                        ->order
                                                        ->user
                                                        ->last_name,
                                                ),

                                            'email' =>
                                                (string) $this
                                                    ->order
                                                    ->user
                                                    ->email,

                                            'phone' =>
                                                (string) (
                                                    $this
                                                        ->order
                                                        ->user
                                                        ->phone
                                                    ?? ''
                                                ),
                                        ],
                            ],
                ),

            'status' =>
                $this->status->value,

            'original_name' =>
                (string) $this->original_name,

            'mime_type' =>
                (string) $this->mime_type,

            'file_size' =>
                (int) $this->file_size,

            'file_available' =>
                $this->hasStoredFile(),

            'file_url' =>
                $this->hasStoredFile()
                    ? route(
                        'api.v1.payment-receipts.file',
                        [
                            'receiptUuid' =>
                                $this->uuid,
                        ],
                    )
                    : null,

            'rejection_reason' =>
                $this->rejection_reason,

            'submitted_at' =>
                $this->submitted_at
                    ?->toISOString(),

            'reviewed_at' =>
                $this->reviewed_at
                    ?->toISOString(),

            'expires_at' =>
                $this->expires_at
                    ?->toISOString(),

            'file_deleted_at' =>
                $this->file_deleted_at
                    ?->toISOString(),

            'reviewed_by' =>
                $this->whenLoaded(
                    'reviewer',
                    fn (): ?array =>
                        $this->reviewer === null
                            ? null
                            : [
                                'id' =>
                                    (int) $this
                                        ->reviewer
                                        ->id,

                                'name' =>
                                    trim(
                                        (string) $this
                                            ->reviewer
                                            ->first_name
                                        .' '.
                                        (string) $this
                                            ->reviewer
                                            ->last_name,
                                    ),
                            ],
                ),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}
