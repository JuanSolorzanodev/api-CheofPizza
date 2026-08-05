<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateIngredientPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prices' => [
                'required',
                'array',
                'max:100',
            ],

            'prices.*.size_id' => [
                'required',
                'integer',
                'distinct',
                'exists:sizes,id',
            ],

            'prices.*.extra_price' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'prices' => 'precios',

            'prices.*.size_id' => 'tamaño',

            'prices.*.extra_price' => 'precio extra',
        ];
    }

    public function messages(): array
    {
        return [
            'prices.*.size_id.distinct' => 'No puedes repetir tamaños.',

            'prices.*.extra_price.min' => 'El precio extra no puede ser negativo.',
        ];
    }
}
