<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Category;
use App\Models\CategorySizePrice;
use App\Models\Pizza;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     first_pizza: Pizza,
 *     second_pizza: Pizza,
 *     size: Size
 * }
 */
function publicCartMergeCatalog(): array
{
    $category = Category::query()->create([
        'category_name' => 'Categoría fusión '.fake()->uuid(),
        'description' => 'Categoría para probar la fusión de carritos.',
    ]);

    $firstPizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Americana fusión '.fake()->uuid(),
        'description' => 'Pizza de prueba.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $secondPizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Hawaiana fusión '.fake()->uuid(),
        'description' => 'Pizza de prueba.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Mediana fusión '.fake()->uuid(),
        'portion' => 8,
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $category->id,
        'size_id' => $size->id,
        'price' => '12.50',
    ]);

    return [
        'first_pizza' => $firstPizza,
        'second_pizza' => $secondPizza,
        'size' => $size,
    ];
}

/**
 * @return array<string, mixed>
 */
function publicCartMergePizzaPayload(
    Pizza $pizza,
    Size $size,
    int $quantity = 1,
): array {
    return [
        'pizza_id' => $pizza->id,
        'size_id' => $size->id,
        'quantity' => $quantity,
        'is_half_and_half' => false,
        'second_pizza_id' => null,
        'customizations' => [],
    ];
}

it(
    'merges an anonymous cart into the authenticated user cart',
    function (): void {
        /** @var TestCase $this */
        [
            'first_pizza' => $pizza,
            'size' => $size,
        ] = publicCartMergeCatalog();

        $customer = User::factory()
            ->customer()
            ->create();

        $guestResponse = $this
            ->getJson('/api/v1/public/cart')
            ->assertOk();

        $guestSession = (string) $guestResponse
            ->headers
            ->get('X-Cart-Session');

        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where(
                'session_id',
                $guestSession,
            )
            ->firstOrFail();

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartMergePizzaPayload(
                    pizza: $pizza,
                    size: $size,
                    quantity: 2,
                ),
                [
                    'X-Cart-Session' => $guestSession,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                25,
            );

        Sanctum::actingAs(
            $customer,
        );

        $this
            ->getJson(
                '/api/v1/public/cart',
                [
                    'X-Cart-Session' => $guestSession,
                ],
            )
            ->assertOk()
            ->assertHeader(
                'X-Cart-Session',
                $guestSession,
            )
            ->assertJsonPath(
                'data.user_id',
                (int) $customer->id,
            )
            ->assertJsonCount(
                1,
                'data.items',
            )
            ->assertJsonPath(
                'data.items.0.pizza.id',
                (int) $pizza->id,
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2,
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                12.5,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                25,
            )
            ->assertJsonPath(
                'data.total',
                25,
            );

        $this->assertDatabaseMissing(
            'carts',
            [
                'id' => $guestCart->id,
            ],
        );

        $userCart = Cart::query()
            ->where(
                'user_id',
                $customer->id,
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $userCart->id,
                'pizza_id' => $pizza->id,
                'size_id' => $size->id,
                'quantity' => 2,
                'unit_price' => '12.50',
                'subtotal' => '25.00',
            ],
        );
    },
);

it(
    'consolidates equivalent items when guest and user carts are merged',
    function (): void {
        /** @var TestCase $this */
        [
            'first_pizza' => $pizza,
            'size' => $size,
        ] = publicCartMergeCatalog();

        $customer = User::factory()
            ->customer()
            ->create();

        /*
         * Primero se crea el carrito anónimo con tres unidades.
         * En este punto todavía no existe autenticación.
         */
        $guestResponse = $this
            ->getJson('/api/v1/public/cart')
            ->assertOk();

        $guestSession = (string) $guestResponse
            ->headers
            ->get('X-Cart-Session');

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartMergePizzaPayload(
                    pizza: $pizza,
                    size: $size,
                    quantity: 3,
                ),
                [
                    'X-Cart-Session' => $guestSession,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                37.5,
            );

        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where(
                'session_id',
                $guestSession,
            )
            ->firstOrFail();

        /*
         * Ahora se autentica el usuario y se crea su carrito con
         * dos unidades de la misma configuración.
         *
         * No enviamos la sesión invitada todavía para evitar que
         * se fusione antes de completar el escenario.
         */
        Sanctum::actingAs(
            $customer,
        );

        $userResponse = $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartMergePizzaPayload(
                    pizza: $pizza,
                    size: $size,
                    quantity: 2,
                ),
            )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                25,
            );

        $userSession = (string) $userResponse
            ->headers
            ->get('X-Cart-Session');

        $userCart = Cart::query()
            ->where(
                'user_id',
                $customer->id,
            )
            ->firstOrFail();

        expect(
            $userCart
                ->cartItems()
                ->count(),
        )->toBe(1);

        /*
         * Al recuperar el carrito autenticado enviando la sesión
         * invitada, ambos carritos deben fusionarse.
         */
        $this
            ->getJson(
                '/api/v1/public/cart',
                [
                    'X-Cart-Session' => $guestSession,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user_id',
                (int) $customer->id,
            )
            ->assertJsonCount(
                1,
                'data.items',
            )
            ->assertJsonPath(
                'data.items.0.pizza.id',
                (int) $pizza->id,
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                5,
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                12.5,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                62.5,
            )
            ->assertJsonPath(
                'data.total',
                62.5,
            );

        $this->assertDatabaseMissing(
            'carts',
            [
                'id' => $guestCart->id,
            ],
        );

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $userCart->id,
                'user_id' => $customer->id,
                'session_id' => $userSession,
                'total' => '62.50',
            ],
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $userCart->id,
                'pizza_id' => $pizza->id,
                'size_id' => $size->id,
                'quantity' => 5,
                'unit_price' => '12.50',
                'subtotal' => '62.50',
            ],
        );

        expect(
            $userCart
                ->fresh()
                ?->cartItems()
                ->count(),
        )->toBe(1);
    },
);

it(
    'keeps different items when guest and user carts are merged',
    function (): void {
        /** @var TestCase $this */
        [
            'first_pizza' => $firstPizza,
            'second_pizza' => $secondPizza,
            'size' => $size,
        ] = publicCartMergeCatalog();

        $customer = User::factory()
            ->customer()
            ->create();

        /*
         * Primero se crea el carrito anónimo con la segunda pizza.
         */
        $guestResponse = $this
            ->getJson('/api/v1/public/cart')
            ->assertOk();

        $guestSession = (string) $guestResponse
            ->headers
            ->get('X-Cart-Session');

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartMergePizzaPayload(
                    pizza: $secondPizza,
                    size: $size,
                    quantity: 2,
                ),
                [
                    'X-Cart-Session' => $guestSession,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                25,
            );

        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where(
                'session_id',
                $guestSession,
            )
            ->firstOrFail();

        /*
         * Después se autentica el usuario y se crea su carrito con
         * una pizza diferente.
         */
        Sanctum::actingAs(
            $customer,
        );

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartMergePizzaPayload(
                    pizza: $firstPizza,
                    size: $size,
                ),
            )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                12.5,
            );

        $userCart = Cart::query()
            ->where(
                'user_id',
                $customer->id,
            )
            ->firstOrFail();

        expect(
            $userCart
                ->cartItems()
                ->count(),
        )->toBe(1);

        /*
         * Se envía la sesión anónima para efectuar la fusión.
         */
        $this
            ->getJson(
                '/api/v1/public/cart',
                [
                    'X-Cart-Session' => $guestSession,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user_id',
                (int) $customer->id,
            )
            ->assertJsonCount(
                2,
                'data.items',
            )
            ->assertJsonPath(
                'data.total',
                37.5,
            );

        $this->assertDatabaseMissing(
            'carts',
            [
                'id' => $guestCart->id,
            ],
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $userCart->id,
                'pizza_id' => $firstPizza->id,
                'size_id' => $size->id,
                'quantity' => 1,
                'unit_price' => '12.50',
                'subtotal' => '12.50',
            ],
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $userCart->id,
                'pizza_id' => $secondPizza->id,
                'size_id' => $size->id,
                'quantity' => 2,
                'unit_price' => '12.50',
                'subtotal' => '25.00',
            ],
        );

        expect(
            $userCart
                ->fresh()
                ?->cartItems()
                ->count(),
        )->toBe(2);

        expect(
            (string) $userCart
                ->fresh()
                ?->total,
        )->toBe('37.50');
    },
);

it(
    'does not merge an anonymous cart from another session',
    function (): void {
        /** @var TestCase $this */
        [
            'first_pizza' => $pizza,
            'size' => $size,
        ] = publicCartMergeCatalog();

        $customer = User::factory()
            ->customer()
            ->create();

        $guestSession = fake()->uuid();

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartMergePizzaPayload(
                    pizza: $pizza,
                    size: $size,
                    quantity: 2,
                ),
                [
                    'X-Cart-Session' => $guestSession,
                ],
            )
            ->assertOk();

        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where(
                'session_id',
                $guestSession,
            )
            ->firstOrFail();

        Sanctum::actingAs(
            $customer,
        );

        $differentSession = fake()->uuid();

        $this
            ->getJson(
                '/api/v1/public/cart',
                [
                    'X-Cart-Session' => $differentSession,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user_id',
                (int) $customer->id,
            )
            ->assertJsonPath(
                'data.items',
                [],
            )
            ->assertJsonPath(
                'data.total',
                0,
            );

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $guestCart->id,
                'user_id' => null,
                'session_id' => $guestSession,
                'total' => '25.00',
            ],
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $guestCart->id,
                'pizza_id' => $pizza->id,
                'quantity' => 2,
            ],
        );
    },
);
