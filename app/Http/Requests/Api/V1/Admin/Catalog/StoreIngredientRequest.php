<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input(
                    'name',
                    ''
                )
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $typeId = $this->integer(
            'ingredient_type_id'
        );

        return [
            'ingredient_type_id' => [
                'required',
                'integer',
                'exists:ingredient_types,id',
            ],

            'name' => [
                'required',
                'string',
                'min:2',
                'max:150',

                Rule::unique(
                    'ingredients',
                    'ingredient_name'
                )->where(
                    static fn ($query) =>
                        $query->where(
                            'ingredient_type_id',
                            $typeId
                        )
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'ingredient_type_id' =>
                'tipo de ingrediente',

            'name' =>
                'nombre del ingrediente',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' =>
                'Ya existe un ingrediente con este nombre dentro del tipo seleccionado.',
        ];
    }
}
