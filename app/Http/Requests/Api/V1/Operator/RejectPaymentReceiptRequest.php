<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operator;

use Illuminate\Foundation\Http\FormRequest;

final class RejectPaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' =>
                'Debes indicar el motivo del rechazo.',

            'reason.min' =>
                'El motivo debe tener al menos 5 caracteres.',

            'reason.max' =>
                'El motivo no puede superar los 500 caracteres.',
        ];
    }

    public function reason(): string
    {
        return trim(
            (string) $this->validated(
                'reason',
            ),
        );
    }
}
