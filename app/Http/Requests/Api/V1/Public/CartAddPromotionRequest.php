<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartAddPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $quantity = (int) $this->input('quantity', 1);
        $selectedItems = $this->input('selected_items');

        if ((!is_array($selectedItems) || empty($selectedItems)) && is_array($this->input('selected_pizza_ids'))) {
            $selectedItems = collect($this->input('selected_pizza_ids'))
                ->map(fn ($pizzaId) => [
                    'pizza_id' => (int) $pizzaId,
                    'customizations' => [],
                ])
                ->values()
                ->all();
        }

        $this->merge([
            'quantity' => max(1, min(10, $quantity)),
            'selected_items' => $selectedItems ?? [],
        ]);
    }

    public function rules(): array
    {
        return [
            'promotion_id' => ['required', 'integer', 'exists:promotions,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],

            'selected_items' => ['required', 'array', 'min:1', 'max:10'],
            'selected_items.*.pizza_id' => ['required', 'integer', 'exists:pizzas,id'],
            'selected_items.*.customizations' => ['nullable', 'array', 'max:40'],

            'selected_items.*.customizations.*.action' => [
                'required',
                Rule::in(['extra', 'remove']),
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

            // compatibilidad vieja
            'selected_pizza_ids' => ['sometimes', 'array', 'min:1', 'max:10'],
            'selected_pizza_ids.*' => ['required', 'integer', 'exists:pizzas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'selected_items.required' => 'Debes seleccionar las pizzas de la promoción.',
            'selected_items.*.customizations.*.action.in' => 'La acción de personalización debe ser extra o remove.',
        ];
    }
}
