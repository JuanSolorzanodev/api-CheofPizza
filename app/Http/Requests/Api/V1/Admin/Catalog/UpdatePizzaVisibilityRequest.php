<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePizzaVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_visible' => $this->boolean(
                'is_visible'
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'is_visible' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'is_visible' => 'visibilidad',
        ];
    }
}
