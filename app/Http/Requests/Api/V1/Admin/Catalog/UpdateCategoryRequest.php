<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCategoryRequest extends FormRequest
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
        ]);
    }

    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',

                Rule::unique(
                    'categories',
                    'category_name'
                )->ignore($category?->id),
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
        ];
    }
}
