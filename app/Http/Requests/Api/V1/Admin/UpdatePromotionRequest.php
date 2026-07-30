<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input(
                    'name',
                    ''
                )
            ),

            'slug' => trim(
                (string) $this->input(
                    'slug',
                    ''
                )
            ),

            'description' => filled(
                $this->input('description')
            )
                ? trim(
                    (string) $this->input(
                        'description'
                    )
                )
                : null,

            'banner_image_url' => filled(
                $this->input(
                    'banner_image_url'
                )
            )
                ? trim(
                    (string) $this->input(
                        'banner_image_url'
                    )
                )
                : null,

            'is_active' => $this->boolean(
                'is_active',
                false
            ),

            'selection_quantity' => max(
                1,
                (int) $this->input(
                    'selection_quantity',
                    1
                )
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $promotion =
            $this->route('promotion');

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:150',

                Rule::unique(
                    'promotions',
                    'promotion_name'
                )->ignore(
                    $promotion?->id
                ),
            ],

            'slug' => [
                'required',
                'string',
                'min:3',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',

                Rule::unique(
                    'promotions',
                    'slug'
                )->ignore(
                    $promotion?->id
                ),
            ],

            'description' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'banner_image_url' => [
                'nullable',
                'url:http,https',
                'max:2048',
            ],

            'type' => [
                'required',

                Rule::in([
                    Promotion::TYPE_FIXED_COMBO,
                    Promotion::TYPE_SIZE_FIXED_PRICE,
                ]),
            ],

            'selection_quantity' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],

            'price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
                'decimal:0,2',
            ],

            'starts_at' => [
                'required',
                'date',
            ],

            'ends_at' => [
                'required',
                'date',
                'after:starts_at',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'details' => [
                'nullable',
                'array',
                'max:20',
            ],

            'details.*.category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'details.*.size_id' => [
                'required',
                'integer',
                'exists:sizes,id',
            ],

            'details.*.required_quantity' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],

            'size_prices' => [
                'nullable',
                'array',
                'max:20',
            ],

            'size_prices.*.size_id' => [
                'required',
                'integer',
                'distinct',
                'exists:sizes,id',
            ],

            'size_prices.*.price' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999.99',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (
                Validator $validator
            ): void {
                $type = (string) $this->input(
                    'type'
                );

                $details = $this->input(
                    'details',
                    []
                );

                $sizePrices = $this->input(
                    'size_prices',
                    []
                );

                if (
                    $type ===
                    Promotion::TYPE_FIXED_COMBO
                ) {
                    $this->validateFixedCombo(
                        $validator,
                        is_array($details)
                            ? $details
                            : []
                    );
                }

                if (
                    $type ===
                    Promotion::TYPE_SIZE_FIXED_PRICE
                ) {
                    $this->validateSizePrices(
                        $validator,
                        is_array($sizePrices)
                            ? $sizePrices
                            : []
                    );
                }
            },
        ];
    }

    /**
     * @param array<int, mixed> $details
     */
    private function validateFixedCombo(
        Validator $validator,
        array $details
    ): void {
        if ($details === []) {
            $validator->errors()->add(
                'details',
                'Agrega al menos una regla al combo.'
            );

            return;
        }

        $price = (float) $this->input(
            'price',
            0
        );

        if ($price <= 0) {
            $validator->errors()->add(
                'price',
                'El combo debe tener un precio mayor a cero.'
            );
        }

        $sizeIds = collect($details)
            ->pluck('size_id')
            ->filter()
            ->map(
                static fn (
                    mixed $id
                ): int =>
                    (int) $id
            )
            ->unique()
            ->values();

        if ($sizeIds->count() !== 1) {
            $validator->errors()->add(
                'details',
                'Todas las reglas del combo deben utilizar el mismo tamaño.'
            );
        }

        $duplicates = collect($details)
            ->groupBy(
                static fn (
                    array $detail
                ): string =>
                    (int) (
                        $detail[
                            'category_id'
                        ] ?? 0
                    )
                    .'|'.
                    (int) (
                        $detail[
                            'size_id'
                        ] ?? 0
                    )
            )
            ->filter(
                static fn (
                    $rows
                ): bool =>
                    $rows->count() > 1
            );

        if ($duplicates->isNotEmpty()) {
            $validator->errors()->add(
                'details',
                'No puedes repetir una misma categoría y tamaño.'
            );
        }

        $requiredTotal =
            (int) collect($details)
                ->sum(
                    'required_quantity'
                );

        $selectionQuantity =
            (int) $this->input(
                'selection_quantity'
            );

        if (
            $requiredTotal !==
            $selectionQuantity
        ) {
            $validator->errors()->add(
                'selection_quantity',
                'La cantidad de selección debe coincidir con la suma de las reglas.'
            );
        }
    }

    /**
     * @param array<int, mixed> $sizePrices
     */
    private function validateSizePrices(
        Validator $validator,
        array $sizePrices
    ): void {
        if ($sizePrices === []) {
            $validator->errors()->add(
                'size_prices',
                'Configura al menos un precio por tamaño.'
            );

            return;
        }

        $duplicates = collect($sizePrices)
            ->groupBy(
                static fn (
                    array $row
                ): int =>
                    (int) (
                        $row['size_id']
                        ?? 0
                    )
            )
            ->filter(
                static fn (
                    $rows
                ): bool =>
                    $rows->count() > 1
            );

        if ($duplicates->isNotEmpty()) {
            $validator->errors()->add(
                'size_prices',
                'No puedes repetir un mismo tamaño.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' =>
                'nombre',

            'slug' =>
                'slug',

            'description' =>
                'descripción',

            'banner_image_url' =>
                'URL del banner',

            'type' =>
                'tipo',

            'selection_quantity' =>
                'cantidad de selección',

            'price' =>
                'precio',

            'starts_at' =>
                'fecha de inicio',

            'ends_at' =>
                'fecha de finalización',

            'is_active' =>
                'estado',

            'details' =>
                'reglas del combo',

            'details.*.category_id' =>
                'categoría',

            'details.*.size_id' =>
                'tamaño',

            'details.*.required_quantity' =>
                'cantidad requerida',

            'size_prices' =>
                'precios por tamaño',

            'size_prices.*.size_id' =>
                'tamaño',

            'size_prices.*.price' =>
                'precio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' =>
                'El nombre de la promoción es obligatorio.',

            'name.unique' =>
                'Ya existe una promoción con este nombre.',

            'slug.required' =>
                'El slug es obligatorio.',

            'slug.unique' =>
                'Ya existe una promoción con este slug.',

            'slug.regex' =>
                'El slug solo puede contener letras minúsculas, números y guiones.',

            'type.required' =>
                'Selecciona el tipo de promoción.',

            'type.in' =>
                'El tipo de promoción seleccionado no es válido.',

            'price.numeric' =>
                'El precio debe ser un valor numérico.',

            'starts_at.required' =>
                'La fecha de inicio es obligatoria.',

            'ends_at.required' =>
                'La fecha de finalización es obligatoria.',

            'ends_at.after' =>
                'La fecha de finalización debe ser posterior al inicio.',

            'details.*.category_id.exists' =>
                'Una de las categorías seleccionadas no existe.',

            'details.*.size_id.exists' =>
                'Uno de los tamaños seleccionados no existe.',

            'size_prices.*.size_id.distinct' =>
                'No puedes repetir tamaños.',

            'size_prices.*.size_id.exists' =>
                'Uno de los tamaños seleccionados no existe.',

            'size_prices.*.price.gt' =>
                'El precio por tamaño debe ser mayor a cero.',
        ];
    }
}
