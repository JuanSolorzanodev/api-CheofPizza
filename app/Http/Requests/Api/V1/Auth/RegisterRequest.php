<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\EcuadorPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim(
                (string) $this->input(
                    'first_name',
                    '',
                ),
            ),

            'last_name' => trim(
                (string) $this->input(
                    'last_name',
                    '',
                ),
            ),

            'email' => strtolower(
                trim(
                    (string) $this->input(
                        'email',
                        '',
                    ),
                ),
            ),

            'phone' => EcuadorPhone::normalize(
                $this->input(
                    'phone',
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
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\pL\pM\s\'-]+$/u',
            ],

            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\pL\pM\s\'-]+$/u',
            ],

            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(
                    'users',
                    'email',
                ),
            ],

            'phone' => [
                'required',
                'string',
                'regex:/^\+5939\d{8}$/',
                Rule::unique(
                    'users',
                    'phone',
                ),
            ],

            'password' => [
                'required',
                'confirmed',

                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers(),
            ],

            'password_confirmation' => [
                'required',
                'string',
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
            'first_name.required' => 'Ingresa tus nombres.',

            'first_name.min' => 'Los nombres deben contener al menos 2 caracteres.',

            'first_name.regex' => 'Los nombres contienen caracteres no permitidos.',

            'last_name.required' => 'Ingresa tus apellidos.',

            'last_name.min' => 'Los apellidos deben contener al menos 2 caracteres.',

            'last_name.regex' => 'Los apellidos contienen caracteres no permitidos.',

            'email.required' => 'Ingresa tu correo electrónico.',

            'email.email' => 'Ingresa un correo electrónico válido.',

            'email.unique' => 'Ya existe una cuenta registrada con este correo.',

            'phone.required' => 'Ingresa tu número de teléfono.',

            'phone.regex' => 'Ingresa un número celular ecuatoriano válido.',

            'phone.unique' => 'Ya existe una cuenta registrada con este teléfono.',

            'password.required' => 'Crea una contraseña.',

            'password.confirmed' => 'Las contraseñas no coinciden.',

            'password_confirmation.required' => 'Confirma tu contraseña.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombres',

            'last_name' => 'apellidos',

            'email' => 'correo electrónico',

            'phone' => 'teléfono',

            'password' => 'contraseña',

            'password_confirmation' => 'confirmación de contraseña',
        ];
    }
}
