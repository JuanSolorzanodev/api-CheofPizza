<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\MachineLearning;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class MachineLearningComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->role_name === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $timezone = (string) config(
            'app.timezone',
            'America/Guayaquil',
        );

        $today = CarbonImmutable::today(
            $timezone,
        );

        /*
         * Cuando el frontend no envía fechas, se consultan
         * automáticamente los últimos siete días.
         */
        $this->merge([
            'date_from' => $this->input(
                'date_from',
                $today
                    ->subDays(6)
                    ->toDateString(),
            ),

            'date_to' => $this->input(
                'date_to',
                $today->toDateString(),
            ),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'date_from' => [
                'required',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required' => 'La fecha inicial es obligatoria.',

            'date_from.date_format' => 'La fecha inicial debe tener el formato YYYY-MM-DD.',

            'date_to.required' => 'La fecha final es obligatoria.',

            'date_to.date_format' => 'La fecha final debe tener el formato YYYY-MM-DD.',

            'date_to.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
        ];
    }

    /**
     * Limita el rango para evitar consolidaciones excesivamente
     * grandes desde una sola petición administrativa.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (
                Validator $validator,
            ): void {
                if (
                    $validator
                        ->errors()
                        ->isNotEmpty()
                ) {
                    return;
                }

                $from = CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    (string) $this->input(
                        'date_from',
                    ),
                );

                $to = CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    (string) $this->input(
                        'date_to',
                    ),
                );

                /*
                 * diffInDays() devuelve 30 para un período
                 * inclusivo de 31 días.
                 */
                if (
                    $from->diffInDays(
                        $to,
                    ) > 30
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'date_to',
                            'El período de comparación no puede superar los 31 días.',
                        );
                }
            },
        ];
    }
}
