<?php

namespace App\DTOs\Builder;

final readonly class ExtrasCalculation
{
    public function __construct(
        public float $total,
        public array $extras,
        public array $removes,
    ) {}
}
