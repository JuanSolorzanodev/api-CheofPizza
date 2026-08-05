<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PaymentTransactionIndexRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * @var list<string>
     */
    private const ALLOWED_TIMEZONES = [
        'America/Guayaquil',
        'UTC',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_METHODS = [
        'cash',
        'transfer',
        'paypal',
    ];

    /**
     * Estados unificados que puede filtrar el administrador.
     *
     * @var list<string>
     */
    private const ALLOWED_STATUSES = [
        'collected',
        'pending',
        'approved',
        'rejected',
        'created',
        'completed',
        'denied',
        'failed',
        'cancelled',
        'refunded',
        'partially_refunded',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' => $this->filled('date_from')
                ? trim((string) $this->query('date_from'))
                : null,

            'date_to' => $this->filled('date_to')
                ? trim((string) $this->query('date_to'))
                : null,

            'timezone' => $this->filled('timezone')
                ? trim((string) $this->query('timezone'))
                : 'America/Guayaquil',

            'method' => $this->filled('method')
                ? strtolower(trim((string) $this->query('method')))
                : null,

            'status' => $this->filled('status')
                ? strtolower(trim((string) $this->query('status')))
                : null,

            'search' => $this->filled('search')
                ? trim((string) $this->query('search'))
                : null,

            'page' => $this->query('page', 1),
            'per_page' => $this->query('per_page', 15),
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
                Rule::in(self::ALLOWED_TIMEZONES),
            ],

            'method' => [
                'nullable',
                'string',
                Rule::in(self::ALLOWED_METHODS),
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(self::ALLOWED_STATUSES),
            ],

            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'page' => [
                'required',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'required',
                'integer',
                Rule::in([10, 15, 25, 50, 100]),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    ! $this->filled('date_from')
                    || ! $this->filled('date_to')
                ) {
                    return;
                }

                $from = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $this->input('date_from'),
                );

                $to = CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    (string) $this->input('date_to'),
                );

                if ($from === false || $to === false) {
                    return;
                }

                $days = (int) $from->diffInDays(
                    $to,
                    absolute: true,
                ) + 1;

                if ($days > self::MAX_RANGE_DAYS) {
                    $validator->errors()->add(
                        'date_to',
                        'El rango consultado no puede superar 366 días.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'fecha inicial',
            'date_to' => 'fecha final',
            'timezone' => 'zona horaria',
            'method' => 'método de pago',
            'status' => 'estado',
            'search' => 'búsqueda',
            'page' => 'página',
            'per_page' => 'elementos por página',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required_with' => 'Debes enviar la fecha inicial cuando especifiques una fecha final.',

            'date_to.required_with' => 'Debes enviar la fecha final cuando especifiques una fecha inicial.',

            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',

            'timezone.in' => 'La zona horaria seleccionada no está permitida.',

            'method.in' => 'El método debe ser cash, transfer o paypal.',

            'status.in' => 'El estado seleccionado no está permitido.',

            'per_page.in' => 'La cantidad por página debe ser 10, 15, 25, 50 o 100.',
        ];
    }
}
