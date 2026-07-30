<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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

            'phone' => trim(
                (string) $this->input(
                    'phone',
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

            'role' => strtolower(
                trim(
                    (string) $this->input(
                        'role',
                        '',
                    ),
                ),
            ),

            'is_active' => $this->boolean(
                'is_active',
                true,
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
            ],

            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'phone' => [
                'required',
                'string',
                'min:7',
                'max:30',
                Rule::unique(
                    'users',
                    'phone',
                ),
            ],

            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique(
                    'users',
                    'email',
                ),
            ],

            'role' => [
                'required',
                Rule::in([
                    'customer',
                    'operator',
                    'admin',
                ]),
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' =>
                'Ya existe un usuario con este correo.',

            'phone.unique' =>
                'Ya existe un usuario con este teléfono.',

            'role.in' =>
                'El rol seleccionado no es válido.',
        ];
    }
}
