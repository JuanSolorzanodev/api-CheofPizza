<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\CashRegister;

use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashMovement
 */
final class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'type' => $this->type->value,

            'amount' => (float) $this->amount,

            'reason' => $this->reason,

            'occurred_at' => $this->occurred_at?->toISOString(),

            'created_by' => [
                'id' => $this->createdBy?->id,

                'name' => $this->createdBy?->full_name,
            ],
        ];
    }
}
