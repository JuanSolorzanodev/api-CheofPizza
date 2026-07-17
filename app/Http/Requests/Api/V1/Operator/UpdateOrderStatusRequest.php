<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operator;

use App\Enums\OrderStatusName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('to_status');

        $this->merge([
            'to_status' => is_string($status)
                ? strtolower(trim($status))
                : $status,

            'note' => is_string($this->input('note'))
                ? trim($this->input('note'))
                : $this->input('note'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'to_status' => [
                'required',
                'string',
                Rule::enum(OrderStatusName::class),
            ],

            'note' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_status.required' =>
                'Debes indicar el nuevo estado del pedido.',

            'to_status.string' =>
                'El estado del pedido debe ser un texto válido.',

            'to_status.enum' =>
                'El estado seleccionado no es válido.',

            'note.string' =>
                'La nota debe ser un texto válido.',

            'note.max' =>
                'La nota no puede superar los 255 caracteres.',
        ];
    }

    public function destinationStatus(): OrderStatusName
    {
        return OrderStatusName::from(
            (string) $this->validated('to_status'),
        );
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        if (!is_string($note)) {
            return null;
        }

        $note = trim($note);

        return $note !== ''
            ? $note
            : null;
    }
}
