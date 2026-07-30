<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use App\Models\Size;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSizeRequest extends FormRequest
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
        /** @var Size|null $size */
        $size = $this->route('size');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:60',

                Rule::unique(
                    'sizes',
                    'size_name'
                )->ignore($size?->id),
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
