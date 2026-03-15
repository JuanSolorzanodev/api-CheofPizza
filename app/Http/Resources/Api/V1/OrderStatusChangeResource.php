<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->fromStatus?->status_name,
            'to' => $this->toStatus?->status_name,
            'changed_at' => optional($this->changed_at)->toISOString(),
            'note' => $this->note,
        ];
    }
}
