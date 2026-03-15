<?php

namespace App\Http\Requests\Api\V1\Operator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperatorOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // el middleware role ya hace el resto
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'], // order_number o texto
            'status' => ['nullable', 'string', Rule::in([
                'pending','confirmed','preparing','ready','on_the_way','delivered','cancelled'
            ])],
            'delivery_type' => ['nullable', 'string', Rule::in(['delivery','pickup'])],
            'payment_method' => ['nullable', 'string', Rule::in(['cash','transfer','card'])],

            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],

            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
