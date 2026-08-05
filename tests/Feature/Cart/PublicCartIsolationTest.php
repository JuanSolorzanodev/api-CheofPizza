<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
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
 *     category: Category,
 *     pizza: Pizza,
 *     size: Size
 * }
 */
function publicCartIsolationCatalog(): array
{
    $category = Category::query()->create([
        'category_name' => 'Categoría carrito '.fake()->uuid(),
        'description' => 'Categoría usada en pruebas de aislamiento.',
    ]);

    $pizza = Pizza::query()->create([
        'category_id' => $category->id,
        'pizza_name' => 'Pizza carrito '.fake()->uuid(),
        'description' => 'Pizza usada en pruebas.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $size = Size::query()->create([
        'size_name' => 'Mediana '.fake()->uuid(),
        'portion' => 8,
    ]);

    CategorySizePrice::query()->create([
        'category_id' => $category->id,
        'size_id' => $size->id,
        'price' => '12.50',
    ]);

    return [
        'category' => $category,
        'pizza' => $pizza,
        'size' => $size,
    ];
}

/**
 * @return array<string, mixed>
 */
function publicCartPizzaPayload(
    Pizza $pizza,
    Size $size,
    int $quantity = 1,
): array {
    return [
        'pizza_id' => $pizza->id,
        'pizza_id_second' => null,
        'size_id' => $size->id,
        'quantity' => $quantity,
        'personalizations' => [],
    ];
}

it(
    'creates an anonymous cart and returns its session header',
    function (): void {
        /** @var TestCase $this */
        $response = $this
            ->getJson('/api/v1/public/cart')
            ->assertOk()
            ->assertHeader(
                'X-Cart-Session',
            )
            ->assertJsonPath(
                'data.user_id',
                null,
            )
            ->assertJsonPath(
                'data.items',
                [],
            )
            ->assertJsonPath(
                'data.total',
                0,
            );

        $sessionId = $response->headers->get(
            'X-Cart-Session',
        );

        expect($sessionId)
            ->toBeString()
            ->not
            ->toBeEmpty();

        $this->assertDatabaseHas(
            'carts',
            [
                'user_id' => null,
                'session_id' => $sessionId,
            ],
        );
    },
);

it(
    'reuses the same anonymous cart session',
    function (): void {
        /** @var TestCase $this */
        [
            'pizza' => $pizza,
            'size' => $size,
        ] = publicCartIsolationCatalog();

        $firstResponse = $this
            ->getJson('/api/v1/public/cart')
            ->assertOk();

        $sessionId = (string) $firstResponse
            ->headers
            ->get('X-Cart-Session');

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartPizzaPayload(
                    pizza: $pizza,
                    size: $size,
                ),
                [
                    'X-Cart-Session' => $sessionId,
                ],
            )
            ->assertOk()
            ->assertHeader(
                'X-Cart-Session',
                $sessionId,
            )
            ->assertJsonPath(
                'data.items.0.pizza.id',
                (int) $pizza->id,
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                1,
            )
            ->assertJsonPath(
                'data.total',
                12.5,
            );

        $this
            ->getJson(
                '/api/v1/public/cart',
                [
                    'X-Cart-Session' => $sessionId,
                ],
            )
            ->assertOk()
            ->assertHeader(
                'X-Cart-Session',
                $sessionId,
            )
            ->assertJsonCount(
                1,
                'data.items',
            )
            ->assertJsonPath(
                'data.items.0.pizza.id',
                (int) $pizza->id,
            );

        expect(
            Cart::query()
                ->where(
                    'session_id',
                    $sessionId,
                )
                ->count(),
        )->toBe(1);
    },
);

it(
    'keeps anonymous cart sessions isolated',
    function (): void {
        /** @var TestCase $this */
        [
            'pizza' => $pizza,
            'size' => $size,
        ] = publicCartIsolationCatalog();

        $firstSession = (string) $this
            ->getJson('/api/v1/public/cart')
            ->headers
            ->get('X-Cart-Session');

        $secondSession = (string) $this
            ->getJson('/api/v1/public/cart')
            ->headers
            ->get('X-Cart-Session');

        expect($firstSession)
            ->not
            ->toBe($secondSession);

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartPizzaPayload(
                    pizza: $pizza,
                    size: $size,
                    quantity: 2,
                ),
                [
                    'X-Cart-Session' => $firstSession,
                ],
            )
            ->assertOk();

        $this
            ->getJson(
                '/api/v1/public/cart',
                [
                    'X-Cart-Session' => $secondSession,
                ],
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items',
                [],
            )
            ->assertJsonPath(
                'data.total',
                0,
            );

        $this->assertDatabaseCount(
            'carts',
            2,
        );

        $secondCart = Cart::query()
            ->where(
                'session_id',
                $secondSession,
            )
            ->firstOrFail();

        expect(
            $secondCart
                ->cartItems()
                ->count(),
        )->toBe(0);
    },
);

it(
    'keeps authenticated user carts isolated',
    function (): void {
        /** @var TestCase $this */
        [
            'pizza' => $pizza,
            'size' => $size,
        ] = publicCartIsolationCatalog();

        $firstUser = User::factory()
            ->customer()
            ->create();

        $secondUser = User::factory()
            ->customer()
            ->create();

        Sanctum::actingAs(
            $firstUser,
        );

        $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartPizzaPayload(
                    pizza: $pizza,
                    size: $size,
                ),
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user_id',
                (int) $firstUser->id,
            )
            ->assertJsonCount(
                1,
                'data.items',
            );

        Sanctum::actingAs(
            $secondUser,
        );

        $this
            ->getJson('/api/v1/public/cart')
            ->assertOk()
            ->assertJsonPath(
                'data.user_id',
                (int) $secondUser->id,
            )
            ->assertJsonPath(
                'data.items',
                [],
            );

        $this->assertDatabaseHas(
            'carts',
            [
                'user_id' => $firstUser->id,
            ],
        );

        $this->assertDatabaseHas(
            'carts',
            [
                'user_id' => $secondUser->id,
            ],
        );
    },
);

it(
    'does not expose or update an item that belongs to another anonymous cart',
    function (): void {
        /** @var TestCase $this */
        [
            'pizza' => $pizza,
            'size' => $size,
        ] = publicCartIsolationCatalog();

        $ownerSession = (string) $this
            ->getJson('/api/v1/public/cart')
            ->headers
            ->get('X-Cart-Session');

        $otherSession = (string) $this
            ->getJson('/api/v1/public/cart')
            ->headers
            ->get('X-Cart-Session');

        $ownerCartResponse = $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartPizzaPayload(
                    pizza: $pizza,
                    size: $size,
                ),
                [
                    'X-Cart-Session' => $ownerSession,
                ],
            )
            ->assertOk();

        $itemId = (int) $ownerCartResponse->json(
            'data.items.0.id',
        );

        $this
            ->putJson(
                "/api/v1/public/cart/items/{$itemId}",
                [
                    'quantity' => 5,
                ],
                [
                    'X-Cart-Session' => $otherSession,
                ],
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'id' => $itemId,
                'quantity' => 1,
            ],
        );

        $ownerCart = Cart::query()
            ->where(
                'session_id',
                $ownerSession,
            )
            ->firstOrFail();

        $otherCart = Cart::query()
            ->where(
                'session_id',
                $otherSession,
            )
            ->firstOrFail();

        expect(
            $ownerCart
                ->cartItems()
                ->count(),
        )->toBe(1);

        expect(
            $otherCart
                ->cartItems()
                ->count(),
        )->toBe(0);
    },
);

it(
    'does not expose or delete an item that belongs to another authenticated user',
    function (): void {
        /** @var TestCase $this */
        [
            'pizza' => $pizza,
            'size' => $size,
        ] = publicCartIsolationCatalog();

        $owner = User::factory()
            ->customer()
            ->create();

        $otherUser = User::factory()
            ->customer()
            ->create();

        Sanctum::actingAs(
            $owner,
        );

        $response = $this
            ->postJson(
                '/api/v1/public/cart/items/pizza',
                publicCartPizzaPayload(
                    pizza: $pizza,
                    size: $size,
                ),
            )
            ->assertOk();

        $itemId = (int) $response->json(
            'data.items.0.id',
        );

        Sanctum::actingAs(
            $otherUser,
        );

        $this
            ->deleteJson(
                "/api/v1/public/cart/items/{$itemId}",
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'id' => $itemId,
                'quantity' => 1,
            ],
        );

        $ownerCart = Cart::query()
            ->where(
                'user_id',
                $owner->id,
            )
            ->firstOrFail();

        $otherCart = Cart::query()
            ->where(
                'user_id',
                $otherUser->id,
            )
            ->firstOrFail();

        expect(
            $ownerCart
                ->cartItems()
                ->count(),
        )->toBe(1);

        expect(
            $otherCart
                ->cartItems()
                ->count(),
        )->toBe(0);
    },
);

it(
    'clears only the selected anonymous cart',
    function (): void {
        /** @var TestCase $this */
        [
            'pizza' => $pizza,
            'size' => $size,
        ] = publicCartIsolationCatalog();

        $firstSession = (string) $this
            ->getJson('/api/v1/public/cart')
            ->headers
            ->get('X-Cart-Session');

        $secondSession = (string) $this
            ->getJson('/api/v1/public/cart')
            ->headers
            ->get('X-Cart-Session');

        foreach (
            [
                $firstSession,
                $secondSession,
            ] as $session
        ) {
            $this
                ->postJson(
                    '/api/v1/public/cart/items/pizza',
                    publicCartPizzaPayload(
                        pizza: $pizza,
                        size: $size,
                    ),
                    [
                        'X-Cart-Session' => $session,
                    ],
                )
                ->assertOk();
        }

        $this
            ->deleteJson(
                '/api/v1/public/cart',
                [],
                [
                    'X-Cart-Session' => $firstSession,
                ],
            )
            ->assertOk()
            ->assertHeader(
                'X-Cart-Session',
                $firstSession,
            )
            ->assertJsonPath(
                'data.items',
                [],
            )
            ->assertJsonPath(
                'data.total',
                0,
            );

        $secondCart = Cart::query()
            ->where(
                'session_id',
                $secondSession,
            )
            ->firstOrFail();

        expect(
            $secondCart
                ->cartItems()
                ->count(),
        )->toBe(1);

        expect(
            CartItem::query()
                ->whereHas(
                    'cart',
                    static fn ($query) => $query->where(
                        'session_id',
                        $firstSession,
                    ),
                )
                ->count(),
        )->toBe(0);
    },
);

it(
    'creates only active carts',
    function (): void {
        /** @var TestCase $this */
        $response = $this
            ->getJson('/api/v1/public/cart')
            ->assertOk();

        $sessionId = (string) $response
            ->headers
            ->get('X-Cart-Session');

        $activeStatus = CartStatus::query()
            ->where(
                'status_name',
                'active',
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'carts',
            [
                'session_id' => $sessionId,
                'cart_status_id' => $activeStatus->id,
            ],
        );
    },
);
