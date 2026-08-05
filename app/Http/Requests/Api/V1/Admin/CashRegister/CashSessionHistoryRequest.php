<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\CashRegister;

use App\Enums\CashSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CashSessionHistoryRequest extends FormRequest
{
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

            'status' => $this->filled('status')
                ? strtolower(trim((string) $this->query('status')))
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

            'status' => [
                'nullable',
                Rule::enum(CashSessionStatus::class),
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.required_with' => 'Debes enviar la fecha inicial cuando especifiques una fecha final.',

            'date_to.required_with' => 'Debes enviar la fecha final cuando especifiques una fecha inicial.',

            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',

            'status.enum' => 'El estado debe ser open o closed.',

            'per_page.in' => 'La cantidad por página debe ser 10, 15, 25, 50 o 100.',
        ];
    }
}
