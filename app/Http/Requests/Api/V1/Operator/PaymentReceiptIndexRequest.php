<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operator;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentReceiptIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'La cantidad por página debe ser un número entero.',

            'per_page.min' => 'La cantidad por página debe ser al menos 1.',

            'per_page.max' => 'La cantidad por página no puede superar 100.',
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated(
            'per_page',
            15,
        );
    }
}
