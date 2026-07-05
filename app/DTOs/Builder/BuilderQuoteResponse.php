<?php

namespace App\DTOs\Builder;

use App\Models\Pizza;

final readonly class BuilderQuoteResponse
{
    public function __construct(
        public Pizza $pizzaA,
        public ?Pizza $pizzaB,
        public int $sizeId,
        public int $quantity,
        public float $basePriceA,
        public float $basePriceB,
        public float $basePrice,
        public float $extrasTotal,
        public float $unitPrice,
        public float $total,
        public array $extrasBreakdown,
        public array $removesBreakdown,
    ) {}
}
