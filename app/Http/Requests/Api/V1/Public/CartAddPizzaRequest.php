<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartAddPizzaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // público
    }

    public function rules(): array
    {
        return [
            'pizza_id' => ['required', 'integer', 'exists:pizzas,id'],
            'size_id'  => ['required', 'integer', 'exists:sizes,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],

            'is_half_and_half' => ['nullable', 'boolean'],
            'second_pizza_id'  => [
                'nullable',
                'integer',
                'exists:pizzas,id',
                'different:pizza_id',
                Rule::requiredIf(fn () => (bool) $this->boolean('is_half_and_half')),
            ],

            // extras: [{ ingredient_id, applies_to }]
            'extras' => ['nullable', 'array', 'max:10'],
            'extras.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'extras.*.applies_to' => ['required', Rule::in(['ALL', 'A', 'B'])],
        ];
    }

    public function messages(): array
    {
        return [
            'second_pizza_id.required' => 'Debes seleccionar el segundo sabor para mitad y mitad.',
            'second_pizza_id.different' => 'El segundo sabor debe ser diferente al primero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => $this->input('quantity', 1),
            'is_half_and_half' => (bool) $this->boolean('is_half_and_half'),
        ]);
    }
}
