<?php

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'delivery_type' => ['required', Rule::in(['delivery', 'pickup'])],

            'address' => [
                Rule::requiredIf(fn () => $this->input('delivery_type') === 'delivery'),
                'nullable',
                'string',
                'max:500',
            ],

            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card'])],

            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_type' => $this->input('delivery_type', 'pickup'),
            'payment_method' => $this->input('payment_method', 'transfer'),
            'address' => $this->input('address'),
            'notes' => $this->input('notes'),
        ]);
    }
}
