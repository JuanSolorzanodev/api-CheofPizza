<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        return [
            'id' => (int) $this->id,

            'role_id' => (int) $this->role_id,

            'first_name' => (string) $this->first_name,

            'last_name' => (string) $this->last_name,

            'full_name' => trim(
                "{$this->first_name} {$this->last_name}",
            ),

            'phone' => (string) $this->phone,

            'email' => (string) $this->email,

            'is_active' => (bool) $this->is_active,

            'role' => $this->whenLoaded(
                'role',
                fn (): array => [
                    'id' => (int) $this->role->id,

                    'name' => (string) $this
                        ->role
                        ->role_name,
                ],
            ),

            'usage' => [
                'carts' => (int) (
                    $this->carts_count ??
                    0
                ),

                'orders' => (int) (
                    $this->orders_count ??
                    0
                ),

                'payments' => (int) (
                    $this->payments_count ??
                    0
                ),
            ],

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
