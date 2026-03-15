<?php

namespace App\Http\Requests\Api\V1\Operator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', Rule::in([
                'pending','confirmed','preparing','ready','on_the_way','delivered','cancelled'
            ])],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
