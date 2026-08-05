<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CartAddPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $quantity = (int) $this->input(
            'quantity',
            1
        );

        $selectedItems =
            $this->input('selected_items');

        if (
            (
                ! is_array($selectedItems) ||
                $selectedItems === []
            ) &&
            is_array(
                $this->input(
                    'selected_pizza_ids'
                )
            )
        ) {
            $selectedItems = collect(
                $this->input(
                    'selected_pizza_ids'
                )
            )
                ->map(
                    static fn (
                        mixed $pizzaId
                    ): array => [
                        'pizza_id' => (int) $pizzaId,

                        'customizations' => [],
                    ]
                )
                ->values()
                ->all();
        }

        $sizeId = $this->input(
            'size_id'
        );

        $this->merge([
            'quantity' => max(
                1,
                min(10, $quantity)
            ),

            'size_id' => $sizeId !== null &&
                $sizeId !== ''
                    ? (int) $sizeId
                    : null,

            'selected_items' => is_array($selectedItems)
                    ? $selectedItems
                    : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'promotion_id' => [
                'required',
                'integer',
                'exists:promotions,id',
            ],

            /*
             * Para combos tradicionales puede ser null,
             * porque el tamaño se obtiene del detalle.
             *
             * Para Hora Loca el servicio exigirá size_id.
             */
            'size_id' => [
                'nullable',
                'integer',
                'exists:sizes,id',
            ],

            'quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:10',
            ],

            'selected_items' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],

            'selected_items.*.pizza_id' => [
                'required',
                'integer',
                'exists:pizzas,id',
            ],

            'selected_items.*.customizations' => [
                'nullable',
                'array',
                'max:40',
            ],

            'selected_items.*.customizations.*.action' => [
                'required',
                Rule::in([
                    'extra',
                    'remove',
                ]),
            ],

            'selected_items.*.customizations.*.ingredient_id' => [
                'required',
                'integer',
                'exists:ingredients,id',
            ],

            'selected_items.*.customizations.*.applies_to' => [
                'nullable',
                Rule::in(['ALL']),
            ],

            /*
             * Compatibilidad con el frontend anterior.
             */
            'selected_pizza_ids' => [
                'sometimes',
                'array',
                'min:1',
                'max:10',
            ],

            'selected_pizza_ids.*' => [
                'required',
                'integer',
                'exists:pizzas,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'size_id.exists' => 'El tamaño seleccionado no existe.',

            'selected_items.required' => 'Debes seleccionar las pizzas de la promoción.',

            'selected_items.*.customizations.*.action.in' => 'La acción de personalización debe ser extra o remove.',
        ];
    }
}
