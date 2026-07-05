<?php

namespace App\Exceptions\Builder;

use App\Exceptions\ApiException;

class InvalidIngredientRemovalException extends ApiException
{
    public function __construct(
        string $message = "You can't remove an ingredient that doesn't belong on the pizza."
    ) {
        parent::__construct(
            message: $message,
            status: 422,
            errorCode: 'INVALID_INGREDIENT_REMOVAL'
        );
    }

    public static function halfA(): self
    {
        return new self(
            'You cannot remove an ingredient that does not belong in half A.'
        );
    }

    public static function halfB(): self
    {
        return new self(
            'You cannot remove an ingredient that does not belong in half B.'
        );
    }

    public static function pizza(): self
    {
        return new self(
            "You can't remove an ingredient that doesn't belong on the pizza."
        );
    }
}
