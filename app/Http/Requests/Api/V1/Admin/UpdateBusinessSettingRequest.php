<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateBusinessSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $business =
            (array) $this->input(
                'business',
                [],
            );

        $store =
            (array) $this->input(
                'store',
                [],
            );

        $delivery =
            (array) $this->input(
                'delivery',
                [],
            );

        $payments =
            (array) $this->input(
                'payments',
                [],
            );

        $whatsapp =
            (array) $this->input(
                'whatsapp',
                [],
            );

        $this->merge([
            'business' => [
                ...$business,

                'name' => $this->trimmed(
                    $business['name'] ?? null,
                ),

                'phone' => $this->nullableTrimmed(
                    $business['phone'] ?? null,
                ),

                'email' => $this->nullableLowercase(
                    $business['email'] ?? null,
                ),

                'address' => $this->nullableTrimmed(
                    $business['address'] ?? null,
                ),
            ],

            'store' => [
                ...$store,

                'accepts_orders' => $this->normalizedBoolean(
                    $store['accepts_orders']
                        ?? null,
                ),

                'closed_message' => $this->nullableTrimmed(
                    $store['closed_message'] ?? null,
                ),

                'currency' => strtoupper(
                    $this->trimmed(
                        $store['currency'] ?? 'USD',
                    ),
                ),

                'timezone' => $this->trimmed(
                    $store['timezone']
                        ?? 'America/Guayaquil',
                ),
            ],

            'delivery' => [
                ...$delivery,

                'pickup_enabled' => $this->normalizedBoolean(
                    $delivery['pickup_enabled']
                        ?? null,
                ),

                'delivery_enabled' => $this->normalizedBoolean(
                    $delivery['delivery_enabled']
                        ?? null,
                ),
            ],

            'payments' => [
                ...$payments,

                'paypal_enabled' => $this->normalizedBoolean(
                    $payments['paypal_enabled']
                        ?? null,
                ),

                'transfer_enabled' => $this->normalizedBoolean(
                    $payments['transfer_enabled']
                        ?? null,
                ),

                'cash_enabled' => $this->normalizedBoolean(
                    $payments['cash_enabled']
                        ?? null,
                ),
            ],

            'whatsapp' => [
                ...$whatsapp,

                'active' => $this->normalizedBoolean(
                    $whatsapp['active']
                        ?? null,
                ),

                'phone' => $this->nullableTrimmed(
                    $whatsapp['phone'] ?? null,
                ),

                'receipt_template' => $this->nullableTrimmed(
                    $whatsapp['receipt_template']
                        ?? null,
                ),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business' => [
                'required',
                'array',
            ],

            'business.name' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],

            'business.phone' => [
                'nullable',
                'string',
                'min:7',
                'max:30',
                'regex:/^[0-9+()\-\s]+$/',
            ],

            'business.email' => [
                'nullable',
                'email:rfc',
                'max:255',
            ],

            'business.address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'store' => [
                'required',
                'array',
            ],

            'store.accepts_orders' => [
                'required',
                'boolean',
            ],

            'store.closed_message' => [
                'nullable',
                'string',
                'max:500',
            ],

            'store.estimated_minutes' => [
                'required',
                'integer',
                'min:5',
                'max:240',
            ],

            'store.currency' => [
                'required',
                Rule::in([
                    'USD',
                ]),
            ],

            'store.timezone' => [
                'required',
                'timezone:all',
                'max:80',
            ],

            'delivery' => [
                'required',
                'array',
            ],

            'delivery.pickup_enabled' => [
                'required',
                'boolean',
            ],

            'delivery.delivery_enabled' => [
                'required',
                'boolean',
            ],

            'delivery.delivery_fee' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2',
            ],

            'delivery.minimum_order' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2',
            ],

            'payments' => [
                'required',
                'array',
            ],

            'payments.paypal_enabled' => [
                'required',
                'boolean',
            ],

            'payments.transfer_enabled' => [
                'required',
                'boolean',
            ],

            'payments.cash_enabled' => [
                'required',
                'boolean',
            ],

            'whatsapp' => [
                'required',
                'array',
            ],

            'whatsapp.active' => [
                'required',
                'boolean',
            ],

            'whatsapp.phone' => [
                'nullable',
                'string',
                'min:7',
                'max:30',
                'regex:/^[0-9+()\-\s]+$/',
                Rule::requiredIf(
                    fn (): bool => $this->normalizedBoolean(
                        $this->input(
                            'whatsapp.active',
                        ),
                    ),
                ),
            ],

            'whatsapp.receipt_template' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Validaciones que dependen de varios campos.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (
                Validator $validator,
            ): void {
                $pickupEnabled =
                    $this->normalizedBoolean(
                        $this->input(
                            'delivery.pickup_enabled',
                        ),
                    );

                $deliveryEnabled =
                    $this->normalizedBoolean(
                        $this->input(
                            'delivery.delivery_enabled',
                        ),
                    );

                if (
                    ! $pickupEnabled
                    && ! $deliveryEnabled
                ) {
                    $validator->errors()->add(
                        'delivery',
                        'Debes habilitar retiro o entrega a domicilio.',
                    );
                }

                $paypalEnabled =
                    $this->normalizedBoolean(
                        $this->input(
                            'payments.paypal_enabled',
                        ),
                    );

                $transferEnabled =
                    $this->normalizedBoolean(
                        $this->input(
                            'payments.transfer_enabled',
                        ),
                    );

                $cashEnabled =
                    $this->normalizedBoolean(
                        $this->input(
                            'payments.cash_enabled',
                        ),
                    );

                if (
                    ! $paypalEnabled
                    && ! $transferEnabled
                    && ! $cashEnabled
                ) {
                    $validator->errors()->add(
                        'payments',
                        'Debes habilitar al menos un método de pago.',
                    );
                }

                if (
                    ! $this->normalizedBoolean(
                        $this->input(
                            'store.accepts_orders',
                        ),
                    )
                    && ! filled(
                        $this->input(
                            'store.closed_message',
                        ),
                    )
                ) {
                    $validator->errors()->add(
                        'store.closed_message',
                        'Indica el mensaje que verá el cliente cuando la tienda esté cerrada.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business.name.required' => 'El nombre del negocio es obligatorio.',

            'business.phone.regex' => 'El teléfono del negocio no tiene un formato válido.',

            'store.estimated_minutes.min' => 'El tiempo estimado debe ser de al menos 5 minutos.',

            'store.estimated_minutes.max' => 'El tiempo estimado no puede superar 240 minutos.',

            'whatsapp.phone.required' => 'El teléfono de WhatsApp es obligatorio cuando el servicio está activo.',

            'whatsapp.phone.regex' => 'El teléfono de WhatsApp no tiene un formato válido.',
        ];
    }

    private function trimmed(
        mixed $value,
    ): string {
        return trim(
            (string) $value,
        );
    }

    private function nullableTrimmed(
        mixed $value,
    ): ?string {
        $normalized =
            trim(
                (string) $value,
            );

        return $normalized === ''
            ? null
            : $normalized;
    }

    private function nullableLowercase(
        mixed $value,
    ): ?string {
        $normalized =
            strtolower(
                trim(
                    (string) $value,
                ),
            );

        return $normalized === ''
            ? null
            : $normalized;
    }

    private function normalizedBoolean(
        mixed $value,
    ): bool {
        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
        );
    }
}
