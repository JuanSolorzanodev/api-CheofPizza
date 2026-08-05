<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(
                trim(
                    (string) $this->input(
                        'email',
                        '',
                    ),
                ),
            ),

            'cart_session_id' => trim(
                (string) $this->input(
                    'cart_session_id',
                    '',
                ),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'max:255',
            ],

            'cart_session_id' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo electrónico.',

            'email.email' => 'Ingresa un correo electrónico válido.',

            'password.required' => 'Ingresa tu contraseña.',
        ];
    }
}
