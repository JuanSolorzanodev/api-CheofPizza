<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
final class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'role_id' => (int) $this->role_id,
            'role' => $this->whenLoaded(
                'role',
                fn (): ?array => $this->role === null
                    ? null
                    : [
                        'id' => (int) $this->role->id,
                        'name' => (string) $this->role->role_name,
                    ],
            ),
            'first_name' => (string) $this->first_name,
            'last_name' => (string) $this->last_name,
            'full_name' => trim(
                (string) $this->first_name.' '.(string) $this->last_name
            ),
            'phone' => $this->phone === null
                ? null
                : (string) $this->phone,
            'email' => (string) $this->email,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
