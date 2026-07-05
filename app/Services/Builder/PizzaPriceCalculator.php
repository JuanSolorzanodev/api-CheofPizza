<?php

namespace App\Services\Builder;

use App\Exceptions\Builder\SizeNotAvailableException;
use App\Models\Pizza;

class PizzaPriceCalculator
{
    public function calculate(
        Pizza $pizzaA,
        ?Pizza $pizzaB,
        int $sizeId
    ): array {

        $priceA = $this->price($pizzaA, $sizeId);

        $priceB = $pizzaB
            ? $this->price($pizzaB, $sizeId)
            : 0;

        return [
            'base_price_a' => $priceA,
            'base_price_b' => $priceB,
            'base_price' => $pizzaB
                ? max($priceA, $priceB)
                : $priceA
        ];
    }

    private function price(
        Pizza $pizza,
        int $sizeId
    ): float {
        $size = $pizza
            ->category
            ->sizes
            ->firstWhere('id', $sizeId);
        if (!$size) {
            throw new SizeNotAvailableException();
        }
        return (float) $size->pivot->price;
    }
}
