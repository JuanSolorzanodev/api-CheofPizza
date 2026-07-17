<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePayPalOrderRequest extends FormRequest
{
    /**
     * Agrega la clave de idempotencia enviada en el header.
     *
     * También utiliza la dirección obtenida desde el mapa cuando
     * el usuario no escribió una dirección manual.
     */
    protected function prepareForValidation(): void
    {
        $deliveryLocation = $this->input('delivery_location');

        $formattedAddress = is_array($deliveryLocation)
            ? ($deliveryLocation['formatted_address'] ?? null)
            : null;

        $address = $this->input('address');

        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),

            'address' => filled($address)
                ? $address
                : $formattedAddress,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => [
                'required',
                'uuid',
            ],

            'delivery_type' => [
                'required',
                'string',
                Rule::in([
                    'pickup',
                    'delivery',
                ]),
            ],

            /*
             * Para pickup la dirección no es necesaria.
             *
             * Para delivery puede llegar escrita manualmente o mediante
             * delivery_location.formatted_address.
             */
            'address' => [
                Rule::requiredIf(
                    fn (): bool => $this->isDelivery()
                ),
                'nullable',
                'string',
                'max:500',
            ],

            'delivery_location' => [
                Rule::requiredIf(
                    fn (): bool => $this->isDelivery()
                ),
                'nullable',
                'array',
            ],

            'delivery_location.lat' => [
                Rule::requiredIf(
                    fn (): bool => $this->isDelivery()
                ),
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'delivery_location.lng' => [
                Rule::requiredIf(
                    fn (): bool => $this->isDelivery()
                ),
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'delivery_location.maps_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'delivery_location.place_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            'delivery_location.reference' => [
                'nullable',
                'string',
                'max:500',
            ],

            'delivery_location.formatted_address' => [
                'nullable',
                'string',
                'max:500',
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
            'idempotency_key.required' =>
                'Debes enviar el encabezado Idempotency-Key.',

            'idempotency_key.uuid' =>
                'El encabezado Idempotency-Key debe contener un UUID válido.',

            'delivery_type.required' =>
                'Debes seleccionar el tipo de entrega.',

            'delivery_type.in' =>
                'El tipo de entrega seleccionado no es válido.',

            'address.required' =>
                'La dirección es obligatoria para la entrega a domicilio.',

            'address.string' =>
                'La dirección debe ser un texto válido.',

            'address.max' =>
                'La dirección no puede superar los 500 caracteres.',

            'delivery_location.required' =>
                'Debes seleccionar una ubicación para la entrega.',

            'delivery_location.array' =>
                'La ubicación de entrega no tiene un formato válido.',

            'delivery_location.lat.required' =>
                'La latitud de entrega es obligatoria.',

            'delivery_location.lat.numeric' =>
                'La latitud de entrega debe ser numérica.',

            'delivery_location.lat.between' =>
                'La latitud de entrega no es válida.',

            'delivery_location.lng.required' =>
                'La longitud de entrega es obligatoria.',

            'delivery_location.lng.numeric' =>
                'La longitud de entrega debe ser numérica.',

            'delivery_location.lng.between' =>
                'La longitud de entrega no es válida.',

            'delivery_location.maps_url.url' =>
                'El enlace de Google Maps no es válido.',

            'delivery_location.maps_url.max' =>
                'El enlace de Google Maps es demasiado extenso.',

            'delivery_location.place_id.max' =>
                'El identificador del lugar es demasiado extenso.',

            'delivery_location.reference.max' =>
                'La referencia no puede superar los 500 caracteres.',

            'delivery_location.formatted_address.max' =>
                'La dirección seleccionada no puede superar los 500 caracteres.',

            'notes.max' =>
                'Las notas no pueden superar los 500 caracteres.',
        ];
    }

    /**
     * Devuelve la clave de idempotencia utilizada para impedir
     * que se creen varias órdenes PayPal por dobles clics o reintentos.
     */
    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }

    /**
     * Datos del checkout que se conservarán en payments.checkout_context.
     *
     * Estos datos se utilizarán después de capturar el pago para crear
     * el pedido definitivo.
     *
     * @return array<string, mixed>
     */
    public function checkoutContext(): array
    {
        $validated = $this->validated();

        unset($validated['idempotency_key']);

        return $validated;
    }

    private function isDelivery(): bool
    {
        return $this->input('delivery_type') === 'delivery';
    }
}
