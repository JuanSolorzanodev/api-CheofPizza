<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Requests\Api\V1\Public\BuilderQuoteRequest;
use App\Services\Builder\BuilderQuoteService;

class BuilderController
{
    public function __construct(private readonly BuilderQuoteService $service) {}

    public function quote(BuilderQuoteRequest $request)
    {
        return response()->json([
            'data' => $this->service->quote($request->validated())
        ]);
    }
}
