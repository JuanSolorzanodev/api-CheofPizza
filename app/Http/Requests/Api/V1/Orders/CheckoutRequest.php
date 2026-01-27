<?php

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

/*     public function rules(): array
    {
        return [
            'delivery_type' => ['required', Rule::in(['delivery', 'pickup'])],

            'address' => [
                Rule::requiredIf(fn () => $this->input('delivery_type') === 'delivery'),
                'nullable',
                'string',
                'max:500',
            ],

            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card'])],

            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
 */

    public function rules(): array
    {
    $isDelivery = fn () => $this->input('delivery_type') === 'delivery';

    return [
        'delivery_type' => ['required', Rule::in(['delivery', 'pickup'])],

        // Ya NO obligamos address como string. Lo dejamos opcional por compatibilidad.
        'address' => ['nullable', 'string', 'max:500'],

        // NUEVO: objeto de ubicación
        'delivery_location' => ['nullable', 'array'],

        'delivery_location.lat' => [
            Rule::requiredIf($isDelivery),
            'numeric',
            'between:-90,90',
        ],
        'delivery_location.lng' => [
            Rule::requiredIf($isDelivery),
            'numeric',
            'between:-180,180',
        ],

        // opcionales para UX y para el operativo luego
        'delivery_location.formatted_address' => ['nullable', 'string', 'max:500'],
        'delivery_location.reference' => ['nullable', 'string', 'max:255'],
        'delivery_location.place_id' => ['nullable', 'string', 'max:255'],
        'delivery_location.maps_url' => ['nullable', 'string', 'max:500'],

        'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card'])],
        'notes' => ['nullable', 'string', 'max:500'],
    ];
    }

/*     protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_type' => $this->input('delivery_type', 'pickup'),
            'payment_method' => $this->input('payment_method', 'transfer'),
            'address' => $this->input('address'),
            'notes' => $this->input('notes'),
        ]);
    } */
   protected function prepareForValidation(): void
    {
    $deliveryType = $this->input('delivery_type', 'pickup');
    $location = $this->input('delivery_location');

    // Si viene delivery_location.formatted_address, lo usamos como address legible
    $formattedAddress = is_array($location) ? ($location['formatted_address'] ?? null) : null;

    $this->merge([
        'delivery_type' => $deliveryType,
        'payment_method' => $this->input('payment_method', 'transfer'),
        'delivery_location' => is_array($location) ? $location : null,

        // address queda como “texto amigable” (opcional)
        'address' => $formattedAddress ?? $this->input('address'),

        'notes' => $this->input('notes'),
    ]);
    }

}
