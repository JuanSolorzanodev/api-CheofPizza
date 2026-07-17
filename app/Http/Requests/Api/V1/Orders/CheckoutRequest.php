<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $deliveryType = $this->input(
            'delivery_type',
            'pickup'
        );

        $location = $this->input(
            'delivery_location'
        );

        $formattedAddress = is_array($location)
            ? ($location['formatted_address'] ?? null)
            : null;

        $this->merge([
            'delivery_type' => $deliveryType,

            'payment_method' => $this->input(
                'payment_method',
                'transfer'
            ),

            'delivery_location' => is_array($location)
                ? $location
                : null,

            'address' => $formattedAddress
                ?? $this->input('address'),

            'notes' => $this->input('notes'),
        ]);
    }

    /**
     * Este endpoint solamente admite métodos offline.
     *
     * Los pagos con tarjeta deben utilizar exclusivamente
     * el flujo protegido de PayPal.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isDelivery = fn (): bool =>
            $this->input('delivery_type') === 'delivery';

        return [
            'delivery_type' => [
                'required',
                'string',
                Rule::in([
                    'delivery',
                    'pickup',
                ]),
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'delivery_location' => [
                Rule::requiredIf($isDelivery),
                'nullable',
                'array',
            ],

            'delivery_location.lat' => [
                Rule::requiredIf($isDelivery),
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'delivery_location.lng' => [
                Rule::requiredIf($isDelivery),
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'delivery_location.formatted_address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'delivery_location.reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'delivery_location.place_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'delivery_location.maps_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            /*
             * IMPORTANTE:
             * card queda expresamente fuera de este endpoint.
             */
            'payment_method' => [
                'required',
                'string',
                Rule::in([
                    'cash',
                    'transfer',
                ]),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_type.required' =>
                'Debes seleccionar un tipo de entrega.',

            'delivery_type.in' =>
                'El tipo de entrega seleccionado no es válido.',

            'delivery_location.required' =>
                'Debes seleccionar una ubicación de entrega.',

            'delivery_location.lat.required' =>
                'La latitud de entrega es obligatoria.',

            'delivery_location.lng.required' =>
                'La longitud de entrega es obligatoria.',

            'payment_method.required' =>
                'Debes seleccionar un método de pago.',

            'payment_method.in' =>
                'Para pagos con tarjeta debes utilizar el flujo seguro de PayPal.',
        ];
    }
}
