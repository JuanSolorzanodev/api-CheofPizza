<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;

class InvalidGoogleTokenException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: "Google's token is not valid.",
            status: 401,
            errorCode: 'INVALID_GOOGLE_TOKEN'
        );
    }
}
