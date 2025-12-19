<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class FirebaseGoogleLoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'phone'    => ['nullable', 'string', 'max:30'], 
        ];
    }
}
