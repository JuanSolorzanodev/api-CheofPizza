<?php

namespace App\Services\Promotion;

use App\Models\Ingredient;
use App\Models\Pizza;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PublicPromotionService
{
    private array $lockedTokens = [
        'sauce_words' => ['pasta', 'salsa'],
        'tomato' => ['tomate'],
        'cheese' => ['queso', 'mozzarella', 'mosarela'],
    ];

    public function activePromotions(): Collection
    {
        return Promotion::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with([
                'promotionDetails' => fn ($q) => $q->orderBy('id')->with(['category', 'size']),
            ])
            ->orderBy('promotion_price')
            ->get();
    }

    public function findActiveBySlugOrFail(string $slug): Promotion
    {
        return Promotion::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with([
                'promotionDetails' => fn ($q) => $q->orderBy('id')->with(['category', 'size']),
            ])
            ->firstOrFail();
    }

    public function findActiveByIdOrFail(int $promotionId): Promotion
    {
        return Promotion::query()
            ->whereKey($promotionId)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with([
                'promotionDetails' => fn ($q) => $q->orderBy('id')->with(['category', 'size']),
            ])
            ->firstOrFail();
    }

    public function validateSelectedItemsForPromotion(Promotion $promotion, array $selectedItems): array
    {
        $details = $promotion->promotionDetails;

        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'promotion_id' => ['La promoción no tiene configuración de detalle.'],
            ]);
        }

        $requiredTotal = (int) $details->sum('required_quantity');

        if (count($selectedItems) !== $requiredTotal) {
            throw ValidationException::withMessages([
                'selected_items' => ["Debes seleccionar exactamente {$requiredTotal} pizzas para esta promoción."],
            ]);
        }

        $selectedPizzaIds = collect($selectedItems)
            ->pluck('pizza_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $selectedPizzas = Pizza::query()
            ->with([
                'category:id,category_name',
                'ingredients:id,ingredient_name',
            ])
            ->whereIn('id', $selectedPizzaIds)
            ->get()
            ->keyBy('id');

        if ($selectedPizzas->count() !== count($selectedPizzaIds)) {
            throw ValidationException::withMessages([
                'selected_items' => ['Una o más pizzas seleccionadas no existen.'],
            ]);
        }

        $expectedByCategory = $details
            ->groupBy('category_id')
            ->map(fn (Collection $rows) => (int) $rows->sum('required_quantity'));

        $selectedByCategory = collect($selectedItems)
            ->map(function ($item) use ($selectedPizzas) {
                $pizza = $selectedPizzas->get((int) $item['pizza_id']);
                return $pizza?->category_id;
            })
            ->filter()
            ->countBy();

        foreach ($expectedByCategory as $categoryId => $expectedQty) {
            $selectedQty = (int) ($selectedByCategory->get($categoryId) ?? 0);

            if ($selectedQty !== $expectedQty) {
                $categoryName = (string) ($details->firstWhere('category_id', $categoryId)?->category?->category_name ?? 'categoría requerida');

                throw ValidationException::withMessages([
                    'selected_items' => ["La promoción requiere {$expectedQty} pizza(s) de la categoría {$categoryName}."],
                ]);
            }
        }

        $sizeIds = $details->pluck('size_id')->unique()->values();

        if ($sizeIds->count() !== 1) {
            throw ValidationException::withMessages([
                'promotion_id' => ['La promoción tiene múltiples tamaños configurados y no puede procesarse automáticamente.'],
            ]);
        }

        $sizeId = (int) $sizeIds->first();

        $ingredientIds = collect($selectedItems)
            ->flatMap(fn ($item) => collect($item['customizations'] ?? [])->pluck('ingredient_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ingredients = Ingredient::query()
            ->with(['sizes' => fn ($q) => $q->where('sizes.id', $sizeId)])
            ->whereIn('id', $ingredientIds)
            ->get()
            ->keyBy('id');

        $builderRules = $this->builderRules();

        $normalizedItems = collect($selectedItems)->map(function ($item, $index) use ($selectedPizzas, $ingredients, $builderRules) {
            $pizzaId = (int) $item['pizza_id'];
            $pizza = $selectedPizzas->get($pizzaId);

            if (!$pizza) {
                throw ValidationException::withMessages([
                    'selected_items' => ['Una pizza seleccionada no es válida.'],
                ]);
            }

            $pizzaIngredients = $pizza->ingredients
                ->map(fn ($ing) => [
                    'id' => (int) $ing->id,
                    'name' => (string) $ing->ingredient_name,
                ])
                ->values();

            $pizzaIngredientIds = $pizzaIngredients->pluck('id')->all();

            $customizations = collect($item['customizations'] ?? [])
                ->map(function ($customization) use ($ingredients, $pizzaIngredientIds, $pizzaIngredients, $builderRules, $index) {
                    $action = strtolower((string) ($customization['action'] ?? ''));
                    $ingredientId = (int) ($customization['ingredient_id'] ?? 0);

                    if (!in_array($action, ['extra', 'remove'], true)) {
                        throw ValidationException::withMessages([
                            "selected_items.{$index}.customizations" => ['Acción de personalización inválida.'],
                        ]);
                    }

                    if (!$ingredients->has($ingredientId)) {
                        throw ValidationException::withMessages([
                            "selected_items.{$index}.customizations" => ['Una personalización contiene un ingrediente inválido.'],
                        ]);
                    }

                    $ingredient = $ingredients->get($ingredientId);
                    $ingredientName = (string) $ingredient->ingredient_name;
                    $isBaseIngredient = in_array($ingredientId, $pizzaIngredientIds, true);

                    if ($action === 'remove') {
                        if (!$isBaseIngredient) {
                            throw ValidationException::withMessages([
                                "selected_items.{$index}.customizations" => ['No puedes quitar un ingrediente que no pertenece a la pizza elegida.'],
                            ]);
                        }

                        if ($this->isLockedBaseIngredient($ingredientName)) {
                            throw ValidationException::withMessages([
                                "selected_items.{$index}.customizations" => ["No puedes quitar el ingrediente base obligatorio: {$ingredientName}."],
                            ]);
                        }
                    }

                    if ($action === 'extra') {
                        if (!$builderRules['allows_extras']) {
                            throw ValidationException::withMessages([
                                "selected_items.{$index}.customizations" => ['Esta promoción no permite extras.'],
                            ]);
                        }

                        if ($isBaseIngredient && !$builderRules['allow_duplicate_ingredients_as_extra']) {
                            throw ValidationException::withMessages([
                                "selected_items.{$index}.customizations" => ["No puedes agregar como extra un ingrediente que ya viene en la pizza: {$ingredientName}."],
                            ]);
                        }
                    }

                    return [
                        'action' => $action,
                        'ingredient_id' => $ingredientId,
                        'applies_to' => 'ALL',
                    ];
                })
                ->values();

            $duplicates = $customizations
                ->groupBy(fn ($c) => $c['action'] . '|' . $c['ingredient_id'])
                ->filter(fn ($rows) => $rows->count() > 1);

            if ($duplicates->isNotEmpty()) {
                throw ValidationException::withMessages([
                    "selected_items.{$index}.customizations" => ['No puedes repetir la misma personalización en una misma pizza.'],
                ]);
            }

            $extraCount = $customizations->where('action', 'extra')->count();

            if ($extraCount > $builderRules['max_extras_per_pizza']) {
                throw ValidationException::withMessages([
                    "selected_items.{$index}.customizations" => ["Solo puedes agregar hasta {$builderRules['max_extras_per_pizza']} extras por pizza en esta promoción."],
                ]);
            }

            return [
                'pizza' => $pizza,
                'customizations' => $customizations
                    ->sortBy([['action', 'asc'], ['ingredient_id', 'asc']])
                    ->values()
                    ->all(),
            ];
        })->values();

        return [
            'size_id' => $sizeId,
            'selected_items' => $normalizedItems->all(),
            'builder_rules' => $builderRules,
        ];
    }

    public function validateSelectedPizzasForPromotion(Promotion $promotion, array $selectedPizzaIds): array
    {
        $selectedItems = collect($selectedPizzaIds)
            ->map(fn ($pizzaId) => [
                'pizza_id' => (int) $pizzaId,
                'customizations' => [],
            ])
            ->values()
            ->all();

        $validated = $this->validateSelectedItemsForPromotion($promotion, $selectedItems);

        return [
            'size_id' => $validated['size_id'],
            'selected_pizzas' => collect($validated['selected_items'])
                ->pluck('pizza')
                ->values(),
        ];
    }

    private function builderRules(): array
    {
        return [
            'allows_extras' => true,
            'allows_remove_ingredients' => true,
            'max_extras_per_pizza' => 8,
            'allow_duplicate_ingredients_as_extra' => false,
        ];
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim($value))
            ? preg_replace('/\p{Mn}/u', '', \Normalizer::normalize(mb_strtolower(trim($value)), \Normalizer::FORM_D)) ?? mb_strtolower(trim($value))
            : '';
    }

    private function isLockedBaseIngredient(string $name): bool
    {
        $normalized = $this->normalizeText($name);

        $isCheese = collect($this->lockedTokens['cheese'])->contains(fn ($token) => str_contains($normalized, $token));
        $hasTomato = collect($this->lockedTokens['tomato'])->contains(fn ($token) => str_contains($normalized, $token));
        $hasSauceWord = collect($this->lockedTokens['sauce_words'])->contains(fn ($token) => str_contains($normalized, $token));

        $isSauce = $hasTomato && $hasSauceWord;

        return $isCheese || $isSauce;
    }
}
