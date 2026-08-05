<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\CashRegister;

use Illuminate\Foundation\Http\FormRequest;

final class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('closing_note')) {
            $this->merge([
                'closing_note' => trim(
                    (string) $this->input(
                        'closing_note'
                    )
                ),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted_cash' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'closing_note' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
