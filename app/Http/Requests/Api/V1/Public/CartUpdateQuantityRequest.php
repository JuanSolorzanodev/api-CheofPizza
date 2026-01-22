<?php

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;

class CartUpdateQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // público, controlamos por sesión/usuario en el servicio
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
