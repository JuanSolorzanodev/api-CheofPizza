<?php

namespace App\Http\Resources\Api\V1\Operator;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorOrderStatusChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->changedBy;

        return [
            'from' => $this->fromStatus?->status_name,
            'to' => $this->toStatus?->status_name,
            'changed_at' => optional($this->changed_at)->toISOString(),
            'note' => $this->note,
            'changed_by' => $user ? [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
            ] : null,
        ];
    }
}
