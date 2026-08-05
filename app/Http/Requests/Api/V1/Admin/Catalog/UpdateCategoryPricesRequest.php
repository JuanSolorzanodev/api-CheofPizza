<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCategoryPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prices' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'prices.*.category_id' => [
                'required',
                'integer',
                'distinct',
                'exists:categories,id',
            ],

            'prices.*.size_id' => [
                'required',
                'integer',
                'exists:sizes,id',
            ],

            'prices.*.price' => [
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
            'prices.*.category_id' => 'categoría',
            'prices.*.size_id' => 'tamaño',
            'prices.*.price' => 'precio',
        ];
    }

    public function messages(): array
    {
        return [
            'prices.*.price.min' => 'El precio no puede ser negativo.',

            'prices.*.price.decimal' => 'El precio debe tener máximo dos decimales.',
        ];
    }
}
