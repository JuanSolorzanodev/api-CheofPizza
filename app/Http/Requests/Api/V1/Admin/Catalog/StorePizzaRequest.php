<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePizzaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input('name', '')
            ),

            'description' => filled(
                $this->input('description')
            )
                ? trim(
                    (string) $this->input('description')
                )
                : null,

            'image_url' => filled(
                $this->input('image_url')
            )
                ? trim(
                    (string) $this->input('image_url')
                )
                : null,

            'is_visible' => $this->boolean(
                'is_visible',
                true
            ),
        ]);
    }

    public function rules(): array
    {
        $categoryId = $this->integer(
            'category_id'
        );

        return [
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'name' => [
                'required',
                'string',
                'min:2',
                'max:150',

                Rule::unique(
                    'pizzas',
                    'pizza_name'
                )->where(
                    static fn ($query) => $query->where(
                        'category_id',
                        $categoryId
                    )
                ),
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'image_url' => [
                'nullable',
                'url:http,https',
                'max:2048',
            ],

            'is_visible' => [
                'required',
                'boolean',
            ],

            'ingredient_ids' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],

            'ingredient_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:ingredients,id',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'categoría',
            'name' => 'nombre',
            'description' => 'descripción',
            'image_url' => 'URL de imagen',
            'is_visible' => 'visibilidad',
            'ingredient_ids' => 'ingredientes',
            'ingredient_ids.*' => 'ingrediente',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una pizza con este nombre dentro de la categoría seleccionada.',

            'ingredient_ids.required' => 'Selecciona al menos un ingrediente.',
            'ingredient_ids.min' => 'Selecciona al menos un ingrediente.',

            'ingredient_ids.*.distinct' => 'No puedes repetir ingredientes.',
        ];
    }
}
