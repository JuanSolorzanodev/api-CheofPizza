<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'receipt' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt.required' =>
                'Debes seleccionar un comprobante.',

            'receipt.file' =>
                'El comprobante seleccionado no es un archivo válido.',

            'receipt.mimes' =>
                'El comprobante debe ser JPG, PNG, WebP o PDF.',

            'receipt.mimetypes' =>
                'El tipo real del comprobante no está permitido.',

            'receipt.max' =>
                'El comprobante no puede superar los 5 MB.',
        ];
    }
}
