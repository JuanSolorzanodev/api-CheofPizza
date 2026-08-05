<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\EcuadorPhone;
use Illuminate\Foundation\Http\FormRequest;

final class FirebaseGoogleLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input(
            'phone',
        );

        $this->merge([
            'id_token' => trim(
                (string) $this->input(
                    'id_token',
                    '',
                ),
            ),

            'phone' => $phone === null
                || trim((string) $phone) === ''
                    ? null
                    : EcuadorPhone::normalize(
                        $phone,
                    ),

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
            'id_token' => [
                'required',
                'string',
            ],

            'phone' => [
                'nullable',
                'string',
                'regex:/^\+5939\d{8}$/',
            ],

            'first_name' => [
                'nullable',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\pL\pM\s\'-]+$/u',
            ],

            'last_name' => [
                'nullable',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\pL\pM\s\'-]+$/u',
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
            'id_token.required' => 'No se recibió la credencial de Google.',

            'phone.regex' => 'Ingresa un número celular ecuatoriano válido.',

            'first_name.regex' => 'Los nombres contienen caracteres no permitidos.',

            'last_name.regex' => 'Los apellidos contienen caracteres no permitidos.',
        ];
    }
}
