<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuilderQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

            // Compatibilidad con frontend viejo
            'extras' => ['nullable', 'array', 'max:20'],
            'extras.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'extras.*.applies_to' => ['required', Rule::in(['ALL', 'A', 'B'])],

            // Nuevo formato recomendado
            'customizations' => ['nullable', 'array', 'max:30'],
            'customizations.*.action' => ['required', Rule::in(['extra', 'remove'])],
            'customizations.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'customizations.*.applies_to' => ['required', Rule::in(['ALL', 'A', 'B'])],
        ];
    }

    public function messages(): array
    {
        return [
            'second_pizza_id.required' => 'Debes seleccionar el segundo sabor para mitad y mitad.',
            'second_pizza_id.different' => 'El segundo sabor debe ser diferente al primero.',
            'customizations.*.action.in' => 'La acción debe ser extra o remove.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $customizations = $this->input('customizations');

        if ((!is_array($customizations) || empty($customizations)) && is_array($this->input('extras'))) {
            $customizations = collect($this->input('extras'))
                ->map(fn ($extra) => [
                    'action' => 'extra',
                    'ingredient_id' => $extra['ingredient_id'] ?? null,
                    'applies_to' => $extra['applies_to'] ?? 'ALL',
                ])
                ->values()
                ->all();
        }

        $this->merge([
            'quantity' => $this->input('quantity', 1),
            'is_half_and_half' => (bool) $this->boolean('is_half_and_half'),
            'customizations' => $customizations ?? [],
        ]);
    }
}
