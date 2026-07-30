<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminCategoryPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,

            'category_id' => (int) $this->category_id,

            'size_id' => (int) $this->size_id,

            'price' => (float) $this->price,

            'category' => $this->whenLoaded(
                'category',
                function (): array {
                    return [
                        'id' => (int) $this->category->id,

                        'name' => (string) $this
                            ->category
                            ->category_name,
                    ];
                },
            ),

            'size' => $this->whenLoaded(
                'size',
                function (): array {
                    return [
                        'id' => (int) $this->size->id,

                        'name' => (string) $this
                            ->size
                            ->size_name,

                        'portion' => (int) $this
                            ->size
                            ->portion,
                    ];
                },
            ),

            'created_at' => $this
                ->created_at
                ?->toISOString(),

            'updated_at' => $this
                ->updated_at
                ?->toISOString(),
        ];
    }
}
