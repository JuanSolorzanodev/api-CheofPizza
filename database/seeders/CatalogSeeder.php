<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\CategorySizePrice;
use App\Models\Ingredient;
use App\Models\IngredientSizePrice;
use App\Models\IngredientType;
use App\Models\PersonalizationAction;
use App\Models\Pizza;
use App\Models\PizzaIngredient;
use App\Models\Size;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * ✅ Imagen por defecto (temporal) para todas las pizzas
         * Pega aquí tu URL real de Cloudinary
         */
        $defaultPizzaImageUrl = 'https://res.cloudinary.com/dertc9kiq/image/upload/v1766285135/pizza_test_flwrtw.png';

        /**
         * 1) Categorías (según menú)
         */
        $catSencillas = Category::updateOrCreate(
            ['category_name' => 'Sencillas'],
            ['description' => 'Pizzas sencillas Cheo F']
        );

        $catEspeciales = Category::updateOrCreate(
            ['category_name' => 'Especiales'],
            ['description' => 'Pizzas especiales Cheo F']
        );

        /**
         * 2) Tamaños y porciones (según menú)
         * Pequeña 8, Mediana 10, Familiar 12, Gigante 14
         */
        $sizes = [
            'Pequeña' => 8,
            'Mediana' => 10,
            'Familiar' => 12,
            'Gigante' => 14,
        ];

        $sizeModels = [];
        foreach ($sizes as $name => $portion) {
            $sizeModels[$name] = Size::updateOrCreate(
                ['size_name' => $name],
                ['portion' => $portion]
            );
        }

        /**
         * 3) Precios por categoría y tamaño (según menú)
         * - Sencillas: 5, 9, 12, 15
         * - Especiales: 7, 13, 16.50, 20
         */
        $priceMatrix = [
            'Sencillas' => [
                'Pequeña' => 5.00,
                'Mediana' => 9.00,
                'Familiar' => 12.00,
                'Gigante' => 15.00,
            ],
            'Especiales' => [
                'Pequeña' => 7.00,
                'Mediana' => 13.00,
                'Familiar' => 16.50,
                'Gigante' => 20.00,
            ],
        ];

        $categories = [$catSencillas, $catEspeciales];
        foreach ($categories as $cat) {
            foreach ($sizeModels as $sizeName => $size) {
                $price = $priceMatrix[$cat->category_name][$sizeName] ?? 0;

                CategorySizePrice::updateOrCreate(
                    ['category_id' => $cat->id, 'size_id' => $size->id],
                    ['price' => $price]
                );
            }
        }

        /**
         * 4) Tipos de ingredientes
         */
        $types = ['Quesos', 'Carnes', 'Vegetales', 'Salsas', 'Extras'];

        $typeModels = [];
        foreach ($types as $t) {
            $typeModels[$t] = IngredientType::updateOrCreate(['type_name' => $t], []);
        }

        /**
         * 5) Ingredientes (según menús)
         */
        $ingredientDefs = [
            // Quesos
            ['type' => 'Quesos', 'name' => 'Queso mosarela'],

            // Salsas
            ['type' => 'Salsas', 'name' => 'Pasta de tomate'],

            // Vegetales
            ['type' => 'Vegetales', 'name' => 'Rodajas de tomate'],
            ['type' => 'Vegetales', 'name' => 'Tomate'],
            ['type' => 'Vegetales', 'name' => 'Champiñones'],
            ['type' => 'Vegetales', 'name' => 'Piña'],
            ['type' => 'Vegetales', 'name' => 'Durazno'],
            ['type' => 'Vegetales', 'name' => 'Cebolla'],
            ['type' => 'Vegetales', 'name' => 'Pimiento'],
            ['type' => 'Vegetales', 'name' => 'Jalapeño'],
            ['type' => 'Vegetales', 'name' => 'Choclo'],
            ['type' => 'Vegetales', 'name' => 'Aceitunas verdes'],
            ['type' => 'Vegetales', 'name' => 'Palmito'],

            // Carnes
            ['type' => 'Carnes', 'name' => 'Jamón'],
            ['type' => 'Carnes', 'name' => 'Tocino'],
            ['type' => 'Carnes', 'name' => 'Salami'],
            ['type' => 'Carnes', 'name' => 'Carne'],
            ['type' => 'Carnes', 'name' => 'Peperoni'],
            ['type' => 'Carnes', 'name' => 'Salchichas'],
            ['type' => 'Carnes', 'name' => 'Salchichas especiales'],
            ['type' => 'Carnes', 'name' => 'Longaniza 100% Chonera'],
        ];

        $ingredientModels = [];
        foreach ($ingredientDefs as $def) {
            $ingredientModels[$def['name']] = Ingredient::updateOrCreate(
                ['ingredient_name' => $def['name']],
                ['ingredient_type_id' => $typeModels[$def['type']]->id]
            );
        }

        /**
         * 6) Precios extra por ingrediente y tamaño (NO viene en el menú)
         */
        $extraBySize = [
            'Pequeña' => 1.00,
            'Mediana' => 1.50,
            'Familiar' => 2.00,
            'Gigante' => 2.50,
        ];

        foreach ($ingredientModels as $ing) {
            foreach ($sizeModels as $sizeName => $size) {
                IngredientSizePrice::updateOrCreate(
                    ['ingredient_id' => $ing->id, 'size_id' => $size->id],
                    ['extra_price' => $extraBySize[$sizeName]]
                );
            }
        }

        /**
         * 7) Acciones de personalización
         */
        $actions = [
            ['action_name' => 'Agregar', 'description' => 'Añadir ingrediente'],
            ['action_name' => 'Quitar', 'description' => 'Eliminar ingrediente'],
            ['action_name' => 'Extra', 'description' => 'Porción extra del ingrediente'],
        ];

        foreach ($actions as $a) {
            PersonalizationAction::updateOrCreate(
                ['action_name' => $a['action_name']],
                ['description' => $a['description']]
            );
        }

        /**
         * 8) Pizzas + ingredientes (según menús)
         */
        $pizzas = [
            // SENCILLAS
            [
                'category_id' => $catSencillas->id,
                'name' => 'Margarita',
                'ingredients' => ['Pasta de tomate', 'Queso mosarela'],
            ],
            [
                'category_id' => $catSencillas->id,
                'name' => 'Americana',
                'ingredients' => ['Tocino', 'Salami', 'Queso mosarela'],
            ],
            [
                'category_id' => $catSencillas->id,
                'name' => 'Clásica',
                'ingredients' => ['Jamón', 'Champiñones', 'Queso mosarela'],
            ],
            [
                'category_id' => $catSencillas->id,
                'name' => 'Napolitana',
                'ingredients' => ['Rodajas de tomate', 'Jamón', 'Queso mosarela'],
            ],
            [
                'category_id' => $catSencillas->id,
                'name' => 'Hawallana',
                'ingredients' => ['Piña', 'Durazno', 'Queso mosarela'],
            ],
            [
                'category_id' => $catSencillas->id,
                'name' => 'Jamon',
                'ingredients' => ['Jamón', 'Queso mosarela'],
            ],
            [
                'category_id' => $catSencillas->id,
                'name' => 'Vegetariana',
                'ingredients' => ['Cebolla', 'Pimiento', 'Champiñones', 'Queso mosarela'],
            ],
            [
                'category_id' => $catSencillas->id,
                'name' => 'Peperoni',
                'ingredients' => ['Peperoni', 'Queso mosarela'],
            ],

            // ESPECIALES
            [
                'category_id' => $catEspeciales->id,
                'name' => 'Especial Cheo',
                'ingredients' => ['Jamón', 'Salami', 'Tocino', 'Carne', 'Pimiento', 'Champiñones', 'Cebolla', 'Queso mosarela'],
            ],
            [
                'category_id' => $catEspeciales->id,
                'name' => 'Tex Mex',
                'ingredients' => ['Pimiento', 'Tocino', 'Carne', 'Jalapeño', 'Queso mosarela'],
            ],
            [
                'category_id' => $catEspeciales->id,
                'name' => 'Campestre',
                'ingredients' => ['Choclo', 'Pimiento', 'Cebolla', 'Tocino', 'Queso mosarela'],
            ],
            [
                'category_id' => $catEspeciales->id,
                'name' => '4 Estaciones',
                'ingredients' => ['Jamón', 'Salami', 'Tocino', 'Salchichas', 'Queso mosarela'],
            ],
            [
                'category_id' => $catEspeciales->id,
                'name' => 'Italiana',
                'ingredients' => ['Peperoni', 'Aceitunas verdes', 'Salchichas especiales', 'Queso mosarela'],
            ],
            [
                'category_id' => $catEspeciales->id,
                'name' => 'Deli Pizza',
                'ingredients' => ['Jamón', 'Salami', 'Tomate', 'Cebolla', 'Pimiento', 'Champiñones', 'Palmito', 'Aceitunas verdes', 'Queso mosarela'],
            ],
            [
                'category_id' => $catEspeciales->id,
                'name' => 'Chonera',
                'ingredients' => ['Longaniza 100% Chonera', 'Cebolla', 'Pimiento', 'Champiñones', 'Queso mosarela'],
            ],
        ];

        foreach ($pizzas as $p) {
            $desc = implode(', ', $p['ingredients']) . '.';

            $pizza = Pizza::updateOrCreate(
                ['pizza_name' => $p['name']],
                [
                    'category_id' => $p['category_id'],
                    'description' => $desc,
                    'is_visible' => true,
                ]
            );

            /**
             * ✅ Asignar imagen por defecto
             * - No pisa si ya tiene imagen (para cuando luego cargues imágenes reales)
             */
            if (blank($pizza->image_url)) {
                $pizza->update(['image_url' => $defaultPizzaImageUrl]);
            }

            // Vincular ingredientes en pivot pizza_ingredients
            foreach ($p['ingredients'] as $ingredientName) {
                $ingredient = $ingredientModels[$ingredientName] ?? null;

                if (!$ingredient) {
                    continue;
                }

                PizzaIngredient::firstOrCreate([
                    'pizza_id' => $pizza->id,
                    'ingredient_id' => $ingredient->id,
                ]);
            }
        }
    }
}
