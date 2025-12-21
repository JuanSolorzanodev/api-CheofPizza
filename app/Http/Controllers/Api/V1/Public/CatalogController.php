<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\IngredientResource;
use App\Http\Resources\Api\V1\PizzaResource;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\Request;

class CatalogController
{
    public function __construct(private readonly CatalogService $service) {}

    /**
     * GET /api/v1/public/catalog/categories
     */
    public function categories()
    {
        return CategoryResource::collection(
            $this->service->categories()
        );
    }
    /**
     * GET /api/v1/public/catalog/ingredients
     */
    public function ingredients()
    {
        return IngredientResource::collection(
            $this->service->ingredients()
        );
    }

    // ✅ 1) Todas las pizzas
    public function pizzas()
    {
        return PizzaResource::collection(
            $this->service->allPizzas()
        );
    }

    // ✅ 2) Solo pizzas sencillas
    public function pizzasSencillas()
    {
        return PizzaResource::collection(
            $this->service->pizzasByCategoryName('Sencillas')
        );
    }

    // ✅ 3) Solo pizzas especiales
    public function pizzasEspeciales()
    {
        return PizzaResource::collection(
            $this->service->pizzasByCategoryName('Especiales')
        );
    }

      public function searchPizzasByName(string $name)
    {
        $name = trim(urldecode($name));

        if ($name === '') {
            return response()->json([
                'message' => 'El nombre de búsqueda no puede estar vacío.'
            ], 422);
        }

        return PizzaResource::collection(
            $this->service->searchPizzasByName($name)
        );
    }
}
