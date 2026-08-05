<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\MachineLearning;

use Illuminate\Foundation\Http\FormRequest;

final class TrainingDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('include_empty_days')) {
            return;
        }

        $normalized = filter_var(
            $this->input('include_empty_days'),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE,
        );

        if ($normalized !== null) {
            $this->merge([
                'include_empty_days' => $normalized,
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],

            'include_empty_days' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.date_format' => 'La fecha inicial debe tener el formato YYYY-MM-DD.',

            'date_to.date_format' => 'La fecha final debe tener el formato YYYY-MM-DD.',

            'date_to.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',

            'limit.integer' => 'El límite debe ser un número entero.',

            'limit.min' => 'El límite debe ser al menos 1.',

            'limit.max' => 'El límite no puede superar 1000 registros.',

            'include_empty_days.boolean' => 'El indicador de días vacíos debe ser verdadero o falso.',
        ];
    }
}
