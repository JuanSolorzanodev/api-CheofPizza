<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreIngredientTypeRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',

                Rule::unique(
                    'ingredient_types',
                    'type_name'
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' =>
                'nombre del tipo',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' =>
                'Ya existe un tipo de ingrediente con este nombre.',
        ];
    }
}
