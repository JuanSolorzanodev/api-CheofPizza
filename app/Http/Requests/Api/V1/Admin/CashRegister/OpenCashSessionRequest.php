<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\CashRegister;

use Illuminate\Foundation\Http\FormRequest;

final class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('opening_note')) {
            $this->merge([
                'opening_note' =>
                    trim(
                        (string) $this->input(
                            'opening_note'
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
            'opening_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'opening_note' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
