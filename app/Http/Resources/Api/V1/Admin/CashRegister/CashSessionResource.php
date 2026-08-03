<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\CashRegister;

use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashSession
 */
final class CashSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' =>
                $this->uuid,

            'status' =>
                $this->status->value,

            'opening_amount' =>
                (float) $this->opening_amount,

            'expected_cash' =>
                $this->expected_cash !== null
                    ? (float) $this->expected_cash
                    : null,

            'counted_cash' =>
                $this->counted_cash !== null
                    ? (float) $this->counted_cash
                    : null,

            'difference' =>
                $this->difference !== null
                    ? (float) $this->difference
                    : null,

            'opened_at' =>
                $this->opened_at?->toISOString(),

            'closed_at' =>
                $this->closed_at?->toISOString(),

            'opening_note' =>
                $this->opening_note,

            'closing_note' =>
                $this->closing_note,

            'opened_by' => [
                'id' =>
                    $this->openedBy?->id,

                'name' =>
                    $this->openedBy?->full_name,
            ],

            'closed_by' =>
                $this->closedBy !== null
                    ? [
                        'id' =>
                            $this->closedBy->id,

                        'name' =>
                            $this->closedBy->full_name,
                    ]
                    : null,
        ];
    }
}
