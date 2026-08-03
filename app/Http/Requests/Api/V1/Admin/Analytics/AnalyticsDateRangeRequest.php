<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AnalyticsDateRangeRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * Zonas horarias permitidas por este sistema.
     *
     * Cheof Pizza opera en Ecuador. Se deja UTC para pruebas,
     * tareas internas y procesos técnicos controlados.
     *
     * @var list<string>
     */
    private const ALLOWED_TIMEZONES = [
        'America/Guayaquil',
        'UTC',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' => filled(
                $this->query('date_from')
            )
                ? trim(
                    (string) $this->query(
                        'date_from'
                    )
                )
                : null,

            'date_to' => filled(
                $this->query('date_to')
            )
                ? trim(
                    (string) $this->query(
                        'date_to'
                    )
                )
                : null,

            'timezone' => filled(
                $this->query('timezone')
            )
                ? trim(
                    (string) $this->query(
                        'timezone'
                    )
                )
                : 'America/Guayaquil',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:date_to',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:date_from',
                'after_or_equal:date_from',
            ],

            'timezone' => [
                'required',
                'string',

                Rule::in(
                    self::ALLOWED_TIMEZONES
                ),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (
                Validator $validator
            ): void {
                if (
                    ! $this->filled('date_from')
                    || ! $this->filled('date_to')
                ) {
                    return;
                }

                $from = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $this->input(
                        'date_from'
                    )
                );

                $to = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $this->input(
                        'date_to'
                    )
                );

                if ($from === false || $to === false) {
                    return;
                }

                $days = (int) $from->diffInDays(
                    $to,
                    absolute: true,
                ) + 1;

                if (
                    $days > self::MAX_RANGE_DAYS
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'date_to',
                            'El rango consultado no puede superar 366 días.'
                        );
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'date_from' =>
            'fecha inicial',

            'date_to' =>
            'fecha final',

            'timezone' =>
            'zona horaria',
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required_with' =>
            'Debes enviar la fecha inicial cuando especifiques una fecha final.',

            'date_to.required_with' =>
            'Debes enviar la fecha final cuando especifiques una fecha inicial.',

            'date_to.after_or_equal' =>
            'La fecha final debe ser igual o posterior a la fecha inicial.',

            'date_from.date_format' =>
            'La fecha inicial debe utilizar el formato YYYY-MM-DD.',

            'date_to.date_format' =>
            'La fecha final debe utilizar el formato YYYY-MM-DD.',

            'timezone.in' =>
            'La zona horaria seleccionada no está permitida.',
        ];
    }
}
