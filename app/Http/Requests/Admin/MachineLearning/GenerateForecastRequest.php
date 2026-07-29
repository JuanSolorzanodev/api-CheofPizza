<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\MachineLearning;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->role_name
            === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'days' => [
                'sometimes',
                'integer',
                'min:1',
                'max:31',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' =>
                'La fecha inicial es obligatoria.',

            'start_date.date_format' =>
                'La fecha inicial debe tener el formato YYYY-MM-DD.',

            'days.integer' =>
                'La cantidad de días debe ser un número entero.',

            'days.min' =>
                'El pronóstico debe incluir al menos un día.',

            'days.max' =>
                'El pronóstico no puede superar los 31 días.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('days')) {
            $this->merge([
                'days' => 7,
            ]);
        }
    }
}
