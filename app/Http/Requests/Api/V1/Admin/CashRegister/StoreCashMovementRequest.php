<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\CashRegister;

use App\Enums\CashMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->filled('type')
                    ? strtolower(
                        trim(
                            (string) $this->input('type')
                        )
                    )
                    : null,

            'reason' => $this->filled('reason')
                    ? trim(
                        (string) $this->input('reason')
                    )
                    : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::enum(CashMovementType::class),
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'reason' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Debes seleccionar el tipo de movimiento.',

            'type.enum' => 'El tipo debe ser income o expense.',

            'amount.gt' => 'El monto debe ser mayor que cero.',

            'reason.required' => 'Debes indicar el motivo del movimiento.',
        ];
    }
}
