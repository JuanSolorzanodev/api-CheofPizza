<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSizeRequest extends FormRequest
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
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:60',

                Rule::unique(
                    'sizes',
                    'size_name'
                ),
            ],

            'portion' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'portion' => 'número de porciones',
        ];
    }
}
