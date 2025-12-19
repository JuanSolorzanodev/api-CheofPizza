<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Services\Catalog\CatalogService;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\PromotionResource;
use App\Http\Resources\Api\V1\SizeResource;
use App\Http\Resources\Api\V1\IngredientResource;
use App\Http\Resources\Api\V1\PizzaResource;
use Illuminate\Http\Request;

class CatalogController
{

    public function __construct(private readonly CatalogService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
                $categoryId = $request->integer('category_id') ?: null;
        $search = trim((string) $request->query('q', '')) ?: null;

        $payload = $this->service->payload($categoryId, $search);

        return response()->json([
            'categories'  => CategoryResource::collection($payload['categories']),
            'sizes'       => SizeResource::collection($payload['sizes']),
            'ingredients' => IngredientResource::collection($payload['ingredients']),
            'pizzas'      => PizzaResource::collection($payload['pizzas']),
            'promotions'  => PromotionResource::collection($payload['promotions']),
        ]);
    }



        public function categories()
    {
        return CategoryResource::collection($this->service->categories());
    }

    public function sizes()
    {
        return SizeResource::collection($this->service->sizes());
    }

    public function ingredients()
    {
        return IngredientResource::collection($this->service->ingredients());
    }

    public function pizzas(Request $request)
    {
        $categoryId = $request->integer('category_id') ?: null;
        $search = trim((string) $request->query('q', '')) ?: null;

        return PizzaResource::collection($this->service->pizzas($categoryId, $search));
    }

    public function promotions()
    {
        return PromotionResource::collection($this->service->activePromotions());
    }

    
}
