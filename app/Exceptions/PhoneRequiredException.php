<?php

namespace App\Exceptions;

use Exception;

class PhoneRequiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: 'A phone number is required to complete registration.',
            status: 422,
            errorCode: 'PHONE_REQUIRED'
        );
    }
}
