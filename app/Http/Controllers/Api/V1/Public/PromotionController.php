<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Resources\Api\V1\PromotionResource;
use App\Services\Promotion\PublicPromotionService;

class PromotionController
{
    public function __construct(private readonly PublicPromotionService $service) {}

    public function index()
    {
        return PromotionResource::collection(
            $this->service->activePromotions()
        );
    }

    public function show(string $slug)
    {
        return new PromotionResource(
            $this->service->findActiveBySlugOrFail($slug)
        );
    }
}
