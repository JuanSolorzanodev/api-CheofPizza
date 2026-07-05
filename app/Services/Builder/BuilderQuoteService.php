<?php

namespace App\Services\Builder;

use App\DTOs\Builder\BuilderQuoteResponse;

class BuilderQuoteService
{
    public function __construct(

        private readonly PizzaLoader $pizzaLoader,
        private readonly PizzaPriceCalculator $priceCalculator,
        private readonly IngredientExtraCalculator $extraCalculator

    ) {}

    public function quote(array $data): BuilderQuoteResponse
    {
        $pizzaA = $this->pizzaLoader->load(
            $data['pizza_id']
        );

        $pizzaB = $this->pizzaLoader->loadSecond(
            $data
        );

        $sizeId = (int) $data['size_id'];

        $quantity = (int) ($data['quantity'] ?? 1);

        $basePrices = $this->priceCalculator->calculate(
            pizzaA: $pizzaA,
            pizzaB: $pizzaB,
            sizeId: $sizeId
        );

        $extras = $this->extraCalculator->calculate(
            pizzaA: $pizzaA,
            pizzaB: $pizzaB,
            sizeId: $sizeId,
            customizations: collect(
                $data['customizations'] ?? []
            )
        );

        $unitPrice = round(
            $basePrices['base_price'] + $extras->total,
            2
        );

        return new BuilderQuoteResponse(
            pizzaA: $pizzaA,
            pizzaB: $pizzaB,
            sizeId: $sizeId,
            quantity: $quantity,
            basePriceA: $basePrices['base_price_a'],
            basePriceB: $basePrices['base_price_b'],
            basePrice: $basePrices['base_price'],
            extrasTotal: $extras->total,
            unitPrice: $unitPrice,
            total: round($unitPrice * $quantity, 2),
            extrasBreakdown: $extras->extras,
            removesBreakdown: $extras->removes
        );
    }
}
