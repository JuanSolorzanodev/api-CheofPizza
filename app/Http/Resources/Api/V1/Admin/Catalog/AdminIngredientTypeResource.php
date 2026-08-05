<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminIngredientTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => (int) $this->id,

            'name' => (string) $this->type_name,

            'ingredients_count' => (int) (
                $this->ingredients_count ?? 0
            ),

            'can_delete' => (bool) (
                $this->can_delete ?? false
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
