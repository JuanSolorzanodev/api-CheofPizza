<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\IngredientResource;
use App\Http\Resources\Api\V1\PizzaResource;
use App\Models\Category;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\Request;

class CatalogController
{
    public function __construct(
        private readonly CatalogService $catalogService
    ) {}


    public function categories()
    {
        return CategoryResource::collection(
            $this->catalogService->getCategoriesWithPrices()
        );
    }

    public function ingredients()
    {
        return IngredientResource::collection(
            $this->catalogService->getIngredientsWithExtraPriceBySize()
        );
    }

    public function pizzas()
    {
        return PizzaResource::collection(
            $this->catalogService->getAllPizzas()
        );
    }

    public function searchCategory(string $categoryName)
    {
        return PizzaResource::collection(
            $this->catalogService->pizzasByCategory($categoryName)
        );
    }

    public function searchPizza(string $pizzaName)
    {
        return PizzaResource::collection(
            $this->catalogService->pizzaByName($pizzaName)
        );
    }
}
