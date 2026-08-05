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
                'present',
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prices' => 'precios',
            'prices.*.size_id' => 'tamaño',
            'prices.*.extra_price' => 'precio extra',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.present' => 'Debes enviar el listado de precios.',
            'prices.array' => 'Los precios deben enviarse como una lista.',
            'prices.max' => 'No puedes enviar más de 100 precios.',

            'prices.*.size_id.required' => 'Debes seleccionar un tamaño.',
            'prices.*.size_id.integer' => 'El tamaño seleccionado no es válido.',
            'prices.*.size_id.distinct' => 'No puedes repetir tamaños.',
            'prices.*.size_id.exists' => 'El tamaño seleccionado no existe.',

            'prices.*.extra_price.required' => 'Debes ingresar el precio extra.',
            'prices.*.extra_price.numeric' => 'El precio extra debe ser numérico.',
            'prices.*.extra_price.min' => 'El precio extra no puede ser negativo.',
            'prices.*.extra_price.max' => 'El precio extra no puede superar 999999.99.',
            'prices.*.extra_price.decimal' => 'El precio extra puede tener como máximo 2 decimales.',
        ];
    }
}
