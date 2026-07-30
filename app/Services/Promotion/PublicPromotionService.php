<?php

declare(strict_types=1);

namespace App\Services\Promotion;

use App\Models\Ingredient;
use App\Models\Pizza;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PublicPromotionService
{
    /**
     * @var array<string, list<string>>
     */
    private array $lockedTokens = [
        'sauce_words' => [
            'pasta',
            'salsa',
        ],

        'tomato' => [
            'tomate',
        ],

        'cheese' => [
            'queso',
            'mozzarella',
            'mosarela',
        ],
    ];

    /**
     * @return Collection<int, Promotion>
     */
    public function activePromotions(): Collection
    {
        return Promotion::query()
            ->where(
                'is_active',
                true
            )
            ->where(
                'starts_at',
                '<=',
                now()
            )
            ->where(
                'ends_at',
                '>=',
                now()
            )
            ->with(
                $this->publicRelations()
            )
            ->orderBy('promotion_name')
            ->get();
    }

    public function findActiveBySlugOrFail(
        string $slug
    ): Promotion {
        return Promotion::query()
            ->where(
                'slug',
                $slug
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'starts_at',
                '<=',
                now()
            )
            ->where(
                'ends_at',
                '>=',
                now()
            )
            ->with(
                $this->publicRelations()
            )
            ->firstOrFail();
    }

    public function findActiveByIdOrFail(
        int $promotionId
    ): Promotion {
        return Promotion::query()
            ->whereKey($promotionId)
            ->where(
                'is_active',
                true
            )
            ->where(
                'starts_at',
                '<=',
                now()
            )
            ->where(
                'ends_at',
                '>=',
                now()
            )
            ->with(
                $this->publicRelations()
            )
            ->firstOrFail();
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     *
     * @return array{
     *     size_id: int,
     *     base_price: float,
     *     selected_items: array<int, array{
     *         pizza: Pizza,
     *         customizations: array<int, array{
     *             action: string,
     *             ingredient_id: int,
     *             applies_to: string
     *         }>
     *     }>,
     *     builder_rules: array<string, mixed>
     * }
     */
    public function validateSelectedItemsForPromotion(
        Promotion $promotion,
        array $selectedItems,
        ?int $requestedSizeId = null
    ): array {
        return match (
            $promotion->promotion_type
        ) {
            Promotion::TYPE_FIXED_COMBO =>
                $this->validateFixedCombo(
                    $promotion,
                    $selectedItems
                ),

            Promotion::TYPE_SIZE_FIXED_PRICE =>
                $this->validateSizeFixedPrice(
                    $promotion,
                    $selectedItems,
                    $requestedSizeId
                ),

            default =>
                throw ValidationException::withMessages([
                    'promotion_id' => [
                        'El tipo de promoción no es compatible.',
                    ],
                ]),
        };
    }

    /**
     * Compatibilidad con código anterior.
     *
     * @param array<int, int> $selectedPizzaIds
     *
     * @return array<string, mixed>
     */
    public function validateSelectedPizzasForPromotion(
        Promotion $promotion,
        array $selectedPizzaIds
    ): array {
        $selectedItems = collect(
            $selectedPizzaIds
        )
            ->map(
                static fn (
                    mixed $pizzaId
                ): array => [
                    'pizza_id' =>
                        (int) $pizzaId,

                    'customizations' =>
                        [],
                ]
            )
            ->values()
            ->all();

        $validated =
            $this->validateSelectedItemsForPromotion(
                $promotion,
                $selectedItems
            );

        return [
            'size_id' =>
                $validated['size_id'],

            'selected_pizzas' =>
                collect(
                    $validated[
                        'selected_items'
                    ]
                )
                    ->pluck('pizza')
                    ->values(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     *
     * @return array<string, mixed>
     */
    private function validateFixedCombo(
        Promotion $promotion,
        array $selectedItems
    ): array {
        $details =
            $promotion->promotionDetails;

        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'promotion_id' => [
                    'La promoción no tiene configuración de detalle.',
                ],
            ]);
        }

        $requiredTotal =
            (int) $details->sum(
                'required_quantity'
            );

        if (
            count($selectedItems) !==
            $requiredTotal
        ) {
            throw ValidationException::withMessages([
                'selected_items' => [
                    "Debes seleccionar exactamente {$requiredTotal} pizzas para esta promoción.",
                ],
            ]);
        }

        $sizeIds = $details
            ->pluck('size_id')
            ->unique()
            ->values();

        if ($sizeIds->count() !== 1) {
            throw ValidationException::withMessages([
                'promotion_id' => [
                    'La promoción tiene múltiples tamaños configurados.',
                ],
            ]);
        }

        $sizeId =
            (int) $sizeIds->first();

        $selectedPizzas =
            $this->loadSelectedPizzas(
                $selectedItems
            );

        $expectedByCategory = $details
            ->groupBy('category_id')
            ->map(
                static fn (
                    Collection $rows
                ): int =>
                    (int) $rows->sum(
                        'required_quantity'
                    )
            );

        $selectedByCategory = collect(
            $selectedItems
        )
            ->map(
                static function (
                    array $item
                ) use (
                    $selectedPizzas
                ): ?int {
                    $pizza =
                        $selectedPizzas->get(
                            (int) $item[
                                'pizza_id'
                            ]
                        );

                    return $pizza
                        ?->category_id;
                }
            )
            ->filter()
            ->countBy();

        foreach (
            $expectedByCategory
            as $categoryId => $expectedQty
        ) {
            $selectedQty =
                (int) (
                    $selectedByCategory
                        ->get($categoryId)
                    ?? 0
                );

            if (
                $selectedQty !==
                $expectedQty
            ) {
                $categoryName =
                    (string) (
                        $details
                            ->firstWhere(
                                'category_id',
                                $categoryId
                            )
                            ?->category
                            ?->category_name
                        ?? 'categoría requerida'
                    );

                throw ValidationException::withMessages([
                    'selected_items' => [
                        "La promoción requiere {$expectedQty} pizza(s) de la categoría {$categoryName}.",
                    ],
                ]);
            }
        }

        return $this->normalizeSelection(
            selectedItems: $selectedItems,
            selectedPizzas:
                $selectedPizzas,
            sizeId: $sizeId,
            basePrice:
                (float) $promotion
                    ->promotion_price
        );
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     *
     * @return array<string, mixed>
     */
    private function validateSizeFixedPrice(
        Promotion $promotion,
        array $selectedItems,
        ?int $requestedSizeId
    ): array {
        $selectionQuantity =
            max(
                1,
                (int) $promotion
                    ->selection_quantity
            );

        if (
            count($selectedItems) !==
            $selectionQuantity
        ) {
            throw ValidationException::withMessages([
                'selected_items' => [
                    "Debes seleccionar exactamente {$selectionQuantity} pizza(s) para esta promoción.",
                ],
            ]);
        }

        if (
            $requestedSizeId === null ||
            $requestedSizeId <= 0
        ) {
            throw ValidationException::withMessages([
                'size_id' => [
                    'Debes seleccionar el tamaño de la promoción.',
                ],
            ]);
        }

        $sizePrice =
            $promotion
                ->sizePrices
                ->firstWhere(
                    'size_id',
                    $requestedSizeId
                );

        if ($sizePrice === null) {
            throw ValidationException::withMessages([
                'size_id' => [
                    'El tamaño seleccionado no está disponible para esta promoción.',
                ],
            ]);
        }

        $selectedPizzas =
            $this->loadSelectedPizzas(
                $selectedItems
            );

        return $this->normalizeSelection(
            selectedItems: $selectedItems,
            selectedPizzas:
                $selectedPizzas,
            sizeId:
                $requestedSizeId,
            basePrice:
                (float) $sizePrice
                    ->fixed_price
        );
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     *
     * @return Collection<int, Pizza>
     */
    private function loadSelectedPizzas(
        array $selectedItems
    ): Collection {
        $selectedPizzaIds = collect(
            $selectedItems
        )
            ->pluck('pizza_id')
            ->map(
                static fn (
                    mixed $id
                ): int =>
                    (int) $id
            )
            ->values()
            ->all();

        $selectedPizzas =
            Pizza::query()
                ->with([
                    'category:id,category_name',

                    'ingredients:id,ingredient_name',
                ])
                ->whereIn(
                    'id',
                    $selectedPizzaIds
                )
                ->get()
                ->keyBy('id');

        /*
         * Se compara contra los IDs únicos porque una
         * promoción puede admitir dos pizzas iguales.
         */
        if (
            $selectedPizzas->count() !==
            count(
                array_unique(
                    $selectedPizzaIds
                )
            )
        ) {
            throw ValidationException::withMessages([
                'selected_items' => [
                    'Una o más pizzas seleccionadas no existen.',
                ],
            ]);
        }

        return $selectedPizzas;
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     * @param Collection<int, Pizza> $selectedPizzas
     *
     * @return array<string, mixed>
     */
    private function normalizeSelection(
        array $selectedItems,
        Collection $selectedPizzas,
        int $sizeId,
        float $basePrice
    ): array {
        if ($basePrice <= 0) {
            throw ValidationException::withMessages([
                'promotion_id' => [
                    'La promoción no tiene un precio válido.',
                ],
            ]);
        }

        $ingredientIds = collect(
            $selectedItems
        )
            ->flatMap(
                static fn (
                    array $item
                ): Collection =>
                    collect(
                        $item[
                            'customizations'
                        ] ?? []
                    )->pluck(
                        'ingredient_id'
                    )
            )
            ->filter()
            ->map(
                static fn (
                    mixed $id
                ): int =>
                    (int) $id
            )
            ->unique()
            ->values();

        $ingredients =
            Ingredient::query()
                ->with([
                    'sizes' =>
                        static fn (
                            $query
                        ) =>
                            $query->where(
                                'sizes.id',
                                $sizeId
                            ),
                ])
                ->whereIn(
                    'id',
                    $ingredientIds
                )
                ->get()
                ->keyBy('id');

        $builderRules =
            $this->builderRules();

        $normalizedItems = collect(
            $selectedItems
        )
            ->map(
                function (
                    array $item,
                    int $index
                ) use (
                    $selectedPizzas,
                    $ingredients,
                    $builderRules
                ): array {
                    $pizzaId =
                        (int) $item[
                            'pizza_id'
                        ];

                    /** @var Pizza|null $pizza */
                    $pizza =
                        $selectedPizzas->get(
                            $pizzaId
                        );

                    if ($pizza === null) {
                        throw ValidationException::withMessages([
                            'selected_items' => [
                                'Una pizza seleccionada no es válida.',
                            ],
                        ]);
                    }

                    $pizzaIngredients =
                        $pizza->ingredients
                            ->map(
                                static fn (
                                    $ingredient
                                ): array => [
                                    'id' =>
                                        (int) $ingredient
                                            ->id,

                                    'name' =>
                                        (string) $ingredient
                                            ->ingredient_name,
                                ]
                            )
                            ->values();

                    $pizzaIngredientIds =
                        $pizzaIngredients
                            ->pluck('id')
                            ->all();

                    $customizations =
                        collect(
                            $item[
                                'customizations'
                            ] ?? []
                        )
                            ->map(
                                function (
                                    array $customization
                                ) use (
                                    $ingredients,
                                    $pizzaIngredientIds,
                                    $builderRules,
                                    $index
                                ): array {
                                    $action =
                                        strtolower(
                                            (string) (
                                                $customization[
                                                    'action'
                                                ] ?? ''
                                            )
                                        );

                                    $ingredientId =
                                        (int) (
                                            $customization[
                                                'ingredient_id'
                                            ] ?? 0
                                        );

                                    if (
                                        !in_array(
                                            $action,
                                            [
                                                'extra',
                                                'remove',
                                            ],
                                            true
                                        )
                                    ) {
                                        throw ValidationException::withMessages([
                                            "selected_items.{$index}.customizations" => [
                                                'Acción de personalización inválida.',
                                            ],
                                        ]);
                                    }

                                    if (
                                        !$ingredients->has(
                                            $ingredientId
                                        )
                                    ) {
                                        throw ValidationException::withMessages([
                                            "selected_items.{$index}.customizations" => [
                                                'Una personalización contiene un ingrediente inválido.',
                                            ],
                                        ]);
                                    }

                                    $ingredient =
                                        $ingredients->get(
                                            $ingredientId
                                        );

                                    $ingredientName =
                                        (string) $ingredient
                                            ->ingredient_name;

                                    $isBaseIngredient =
                                        in_array(
                                            $ingredientId,
                                            $pizzaIngredientIds,
                                            true
                                        );

                                    if (
                                        $action ===
                                        'remove'
                                    ) {
                                        if (
                                            !$isBaseIngredient
                                        ) {
                                            throw ValidationException::withMessages([
                                                "selected_items.{$index}.customizations" => [
                                                    'No puedes quitar un ingrediente que no pertenece a la pizza elegida.',
                                                ],
                                            ]);
                                        }

                                        if (
                                            $this->isLockedBaseIngredient(
                                                $ingredientName
                                            )
                                        ) {
                                            throw ValidationException::withMessages([
                                                "selected_items.{$index}.customizations" => [
                                                    "No puedes quitar el ingrediente base obligatorio: {$ingredientName}.",
                                                ],
                                            ]);
                                        }
                                    }

                                    if (
                                        $action ===
                                        'extra'
                                    ) {
                                        if (
                                            !$builderRules[
                                                'allows_extras'
                                            ]
                                        ) {
                                            throw ValidationException::withMessages([
                                                "selected_items.{$index}.customizations" => [
                                                    'Esta promoción no permite extras.',
                                                ],
                                            ]);
                                        }

                                        if (
                                            $isBaseIngredient &&
                                            !$builderRules[
                                                'allow_duplicate_ingredients_as_extra'
                                            ]
                                        ) {
                                            throw ValidationException::withMessages([
                                                "selected_items.{$index}.customizations" => [
                                                    "No puedes agregar como extra un ingrediente que ya viene en la pizza: {$ingredientName}.",
                                                ],
                                            ]);
                                        }
                                    }

                                    return [
                                        'action' =>
                                            $action,

                                        'ingredient_id' =>
                                            $ingredientId,

                                        'applies_to' =>
                                            'ALL',
                                    ];
                                }
                            )
                            ->values();

                    $duplicates =
                        $customizations
                            ->groupBy(
                                static fn (
                                    array $row
                                ): string =>
                                    $row['action']
                                    .'|'.
                                    $row[
                                        'ingredient_id'
                                    ]
                            )
                            ->filter(
                                static fn (
                                    Collection $rows
                                ): bool =>
                                    $rows->count() > 1
                            );

                    if (
                        $duplicates
                            ->isNotEmpty()
                    ) {
                        throw ValidationException::withMessages([
                            "selected_items.{$index}.customizations" => [
                                'No puedes repetir la misma personalización en una misma pizza.',
                            ],
                        ]);
                    }

                    $extraCount =
                        $customizations
                            ->where(
                                'action',
                                'extra'
                            )
                            ->count();

                    if (
                        $extraCount >
                        $builderRules[
                            'max_extras_per_pizza'
                        ]
                    ) {
                        throw ValidationException::withMessages([
                            "selected_items.{$index}.customizations" => [
                                "Solo puedes agregar hasta {$builderRules['max_extras_per_pizza']} extras por pizza.",
                            ],
                        ]);
                    }

                    return [
                        'pizza' =>
                            $pizza,

                        'customizations' =>
                            $customizations
                                ->sortBy([
                                    [
                                        'action',
                                        'asc',
                                    ],
                                    [
                                        'ingredient_id',
                                        'asc',
                                    ],
                                ])
                                ->values()
                                ->all(),
                    ];
                }
            )
            ->values();

        return [
            'size_id' =>
                $sizeId,

            'base_price' =>
                round(
                    $basePrice,
                    2
                ),

            'selected_items' =>
                $normalizedItems->all(),

            'builder_rules' =>
                $builderRules,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function builderRules(): array
    {
        return [
            'allows_extras' =>
                true,

            'allows_remove_ingredients' =>
                true,

            'max_extras_per_pizza' =>
                8,

            'allow_duplicate_ingredients_as_extra' =>
                false,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function publicRelations(): array
    {
        return [
            'promotionDetails' =>
                static fn (
                    $query
                ) =>
                    $query
                        ->orderBy('id')
                        ->with([
                            'category',
                            'size',
                        ]),

            'sizePrices' =>
                static fn (
                    $query
                ) =>
                    $query
                        ->with('size')
                        ->join(
                            'sizes',
                            'sizes.id',
                            '=',
                            'promotion_size_prices.size_id'
                        )
                        ->select(
                            'promotion_size_prices.*'
                        )
                        ->orderBy(
                            'sizes.portion'
                        ),
        ];
    }

    private function normalizeText(
        string $value
    ): string {
        $normalized =
            mb_strtolower(
                trim($value)
            );

        if ($normalized === '') {
            return '';
        }

        if (
            class_exists(
                \Normalizer::class
            )
        ) {
            $normalized =
                \Normalizer::normalize(
                    $normalized,
                    \Normalizer::FORM_D
                ) ?: $normalized;
        }

        return preg_replace(
            '/\p{Mn}/u',
            '',
            $normalized
        ) ?? $normalized;
    }

    private function isLockedBaseIngredient(
        string $name
    ): bool {
        $normalized =
            $this->normalizeText(
                $name
            );

        $isCheese = collect(
            $this->lockedTokens[
                'cheese'
            ]
        )->contains(
            static fn (
                string $token
            ): bool =>
                str_contains(
                    $normalized,
                    $token
                )
        );

        $hasTomato = collect(
            $this->lockedTokens[
                'tomato'
            ]
        )->contains(
            static fn (
                string $token
            ): bool =>
                str_contains(
                    $normalized,
                    $token
                )
        );

        $hasSauceWord = collect(
            $this->lockedTokens[
                'sauce_words'
            ]
        )->contains(
            static fn (
                string $token
            ): bool =>
                str_contains(
                    $normalized,
                    $token
                )
        );

        return
            $isCheese ||
            (
                $hasTomato &&
                $hasSauceWord
            );
    }
}
