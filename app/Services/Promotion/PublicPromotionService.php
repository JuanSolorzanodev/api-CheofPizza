<?php

namespace App\Services\Promotion;

use App\Models\Pizza;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PublicPromotionService
{
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

    public function validateSelectedPizzasForPromotion(Promotion $promotion, array $selectedPizzaIds): array
    {
        $details = $promotion->promotionDetails;

        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'promotion_id' => ['La promoción no tiene configuración de detalle.'],
            ]);
        }

        $requiredTotal = (int) $details->sum('required_quantity');

        if (count($selectedPizzaIds) !== $requiredTotal) {
            throw ValidationException::withMessages([
                'selected_pizza_ids' => ["Debes seleccionar exactamente {$requiredTotal} pizzas para esta promoción."],
            ]);
        }

        $selectedPizzas = Pizza::query()
            ->with('category:id,category_name')
            ->whereIn('id', $selectedPizzaIds)
            ->get();

        if ($selectedPizzas->count() !== count($selectedPizzaIds)) {
            throw ValidationException::withMessages([
                'selected_pizza_ids' => ['Una o más pizzas seleccionadas no existen.'],
            ]);
        }

        $expectedByCategory = $details
            ->groupBy('category_id')
            ->map(fn (Collection $rows) => (int) $rows->sum('required_quantity'));

        $selectedByCategory = $selectedPizzas
            ->groupBy('category_id')
            ->map(fn (Collection $rows) => (int) $rows->count());

        foreach ($expectedByCategory as $categoryId => $expectedQty) {
            $selectedQty = (int) ($selectedByCategory->get($categoryId) ?? 0);

            if ($selectedQty !== $expectedQty) {
                $categoryName = (string) ($details->firstWhere('category_id', $categoryId)?->category?->category_name ?? 'categoría requerida');

                throw ValidationException::withMessages([
                    'selected_pizza_ids' => ["La promoción requiere {$expectedQty} pizza(s) de la categoría {$categoryName}."],
                ]);
            }
        }

        $unexpectedCategories = $selectedByCategory->keys()->diff($expectedByCategory->keys());
        if ($unexpectedCategories->isNotEmpty()) {
            throw ValidationException::withMessages([
                'selected_pizza_ids' => ['Has seleccionado pizzas de categorías no permitidas por la promoción.'],
            ]);
        }

        $sizeIds = $details->pluck('size_id')->unique()->values();
        if ($sizeIds->count() !== 1) {
            throw ValidationException::withMessages([
                'promotion_id' => ['La promoción tiene múltiples tamaños configurados y no puede procesarse automáticamente.'],
            ]);
        }

        return [
            'size_id' => (int) $sizeIds->first(),
            'selected_pizzas' => $selectedPizzas->sortBy('category_id')->values(),
        ];
    }
}
