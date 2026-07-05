<?php

namespace App\Exceptions\Builder;

use App\Exceptions\ApiException;

class SizeNotAvailableException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: 'The selected size is not available for this pizza.',
            status: 422,
            errorCode: 'SIZE_NOT_AVAILABLE'
        );
    }
}
