<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Requests\Api\V1\Public\BuilderQuoteRequest;
use App\Http\Resources\Api\V1\BuilderQuoteResource;
use App\Services\Builder\BuilderQuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BuilderController
{
    public function __construct(
        private readonly BuilderQuoteService $builderService,
        private readonly ApiResponse $response,
    ) {}

    public function quote(BuilderQuoteRequest $request): JsonResponse {

        $quote = $this->builderService->quote(
            $request->validated()
        );

        return $this->response->success(
            data: new BuilderQuoteResource($quote),
            message: 'Quote generated successfully.'
        );
    }

}
