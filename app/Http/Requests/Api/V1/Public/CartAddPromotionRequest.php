<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;

class CartAddPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'promotion_id' => ['required', 'integer', 'exists:promotions,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'selected_pizza_ids' => ['required', 'array', 'min:1', 'max:10'],
            'selected_pizza_ids.*' => ['required', 'integer', 'distinct', 'exists:pizzas,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => $this->input('quantity', 1),
        ]);
    }
}
