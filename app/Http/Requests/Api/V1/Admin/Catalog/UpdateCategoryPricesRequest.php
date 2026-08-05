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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prices' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            /*
             * Una categoría puede aparecer varias veces siempre que
             * cada registro corresponda a un tamaño diferente.
             *
             * La combinación category_id + size_id se valida en
             * AdminCatalogService::validateUniquePricePairs().
             */
            'prices.*.category_id' => [
                'required',
                'integer',
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prices' => 'precios',
            'prices.*.category_id' => 'categoría',
            'prices.*.size_id' => 'tamaño',
            'prices.*.price' => 'precio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.required' => 'Debes enviar al menos un precio.',
            'prices.array' => 'Los precios deben enviarse como una lista.',
            'prices.min' => 'Debes enviar al menos un precio.',
            'prices.max' => 'No puedes enviar más de 500 precios.',

            'prices.*.category_id.required' => 'Debes seleccionar una categoría.',
            'prices.*.category_id.integer' => 'La categoría seleccionada no es válida.',
            'prices.*.category_id.exists' => 'La categoría seleccionada no existe.',

            'prices.*.size_id.required' => 'Debes seleccionar un tamaño.',
            'prices.*.size_id.integer' => 'El tamaño seleccionado no es válido.',
            'prices.*.size_id.exists' => 'El tamaño seleccionado no existe.',

            'prices.*.price.required' => 'Debes ingresar el precio.',
            'prices.*.price.numeric' => 'El precio debe ser numérico.',
            'prices.*.price.min' => 'El precio no puede ser negativo.',
            'prices.*.price.max' => 'El precio no puede superar 999999.99.',
            'prices.*.price.decimal' => 'El precio debe tener máximo dos decimales.',
        ];
    }
}
