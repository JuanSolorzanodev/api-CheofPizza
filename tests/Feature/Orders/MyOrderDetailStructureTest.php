<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\Ingredient;
use App\Models\IngredientType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPersonalization;
use App\Models\OrderPromotionItem;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\PersonalizationAction;
use App\Models\Pizza;
use App\Models\Promotion;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     delivery_type: DeliveryType,
 *     payment_method: PaymentMethod,
 *     pending_status: OrderStatus,
 *     preparing_status: OrderStatus,
 *     delivered_status: OrderStatus,
 *     extra_action: PersonalizationAction,
 *     remove_action: PersonalizationAction
 * }
 */
function myOrderDetailReferences(): array
{
    $deliveryType = DeliveryType::query()->firstOrCreate([
        'delivery_type_name' => 'delivery',
    ]);

    $paymentMethod = PaymentMethod::query()->firstOrCreate(
        [
            'name' => 'cash',
        ],
        [
            'description' => 'Pago en efectivo',
            'active' => true,
        ],
    );

    $pendingStatus = OrderStatus::query()->firstOrCreate([
        'status_name' => 'pending',
    ]);

    $preparingStatus = OrderStatus::query()->firstOrCreate([
        'status_name' => 'preparing',
    ]);

    $deliveredStatus = OrderStatus::query()->firstOrCreate([
        'status_name' => 'delivered',
    ]);

    $extraAction = PersonalizationAction::query()->firstOrCreate(
        [
            'action_name' => 'extra',
        ],
        [
            'description' => 'Agregar ingrediente.',
        ],
    );

    $removeAction = PersonalizationAction::query()->firstOrCreate(
        [
            'action_name' => 'remove',
        ],
        [
            'description' => 'Quitar ingrediente.',
        ],
    );

    return [
        'delivery_type' => $deliveryType,
        'payment_method' => $paymentMethod,
        'pending_status' => $pendingStatus,
        'preparing_status' => $preparingStatus,
        'delivered_status' => $deliveredStatus,
        'extra_action' => $extraAction,
        'remove_action' => $removeAction,
    ];
}

/**
 * @return array{
 *     traditional_category: Category,
 *     special_category: Category,
 *     small_size: Size,
 *     medium_size: Size,
 *     large_size: Size,
 *     americana: Pizza,
 *     supreme: Pizza,
 *     hawaiian: Pizza,
 *     pepperoni: Pizza,
 *     cheese: Ingredient,
 *     onion: Ingredient,
 *     bacon: Ingredient,
 *     promotion: Promotion
 * }
 */
function myOrderDetailCatalog(): array
{
    $traditionalCategory = Category::query()->create([
        'category_name' => 'Tradicionales detalle '.fake()->uuid(),
        'description' => 'Categoría tradicional para pruebas.',
    ]);

    $specialCategory = Category::query()->create([
        'category_name' => 'Especiales detalle '.fake()->uuid(),
        'description' => 'Categoría especial para pruebas.',
    ]);

    $smallSize = Size::query()->create([
        'size_name' => 'Pequeña detalle '.fake()->uuid(),
        'portion' => 4,
    ]);

    $mediumSize = Size::query()->create([
        'size_name' => 'Mediana detalle '.fake()->uuid(),
        'portion' => 8,
    ]);

    $largeSize = Size::query()->create([
        'size_name' => 'Grande detalle '.fake()->uuid(),
        'portion' => 12,
    ]);

    $americana = Pizza::query()->create([
        'category_id' => $traditionalCategory->id,
        'pizza_name' => 'Americana',
        'description' => 'Pizza americana.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $supreme = Pizza::query()->create([
        'category_id' => $specialCategory->id,
        'pizza_name' => 'Suprema',
        'description' => 'Pizza suprema.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $hawaiian = Pizza::query()->create([
        'category_id' => $traditionalCategory->id,
        'pizza_name' => 'Hawaiana',
        'description' => 'Pizza hawaiana.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $pepperoni = Pizza::query()->create([
        'category_id' => $traditionalCategory->id,
        'pizza_name' => 'Pepperoni',
        'description' => 'Pizza pepperoni.',
        'image_url' => null,
        'is_visible' => true,
    ]);

    $ingredientType = IngredientType::query()->create([
        'type_name' => 'Ingredientes detalle '.fake()->uuid(),
    ]);

    $cheese = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Queso extra',
    ]);

    $onion = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Cebolla',
    ]);

    $bacon = Ingredient::query()->create([
        'ingredient_type_id' => $ingredientType->id,
        'ingredient_name' => 'Tocino',
    ]);

    $promotion = Promotion::query()->create([
        'promotion_name' => 'Combo familiar',
        'slug' => 'combo-familiar-detalle-'.fake()->uuid(),
        'description' => 'Combo utilizado en pruebas.',
        'banner_image_url' => null,
        'promotion_type' => Promotion::TYPE_FIXED_COMBO,
        'selection_quantity' => 2,
        'promotion_price' => '22.00',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ]);

    return [
        'traditional_category' => $traditionalCategory,
        'special_category' => $specialCategory,
        'small_size' => $smallSize,
        'medium_size' => $mediumSize,
        'large_size' => $largeSize,
        'americana' => $americana,
        'supreme' => $supreme,
        'hawaiian' => $hawaiian,
        'pepperoni' => $pepperoni,
        'cheese' => $cheese,
        'onion' => $onion,
        'bacon' => $bacon,
        'promotion' => $promotion,
    ];
}

function createDetailedCustomerOrder(
    User $customer,
): Order {
    $references = myOrderDetailReferences();

    return Order::query()->create([
        'order_number' => 'CH-DETAIL-STRUCTURE-'.fake()->unique()->numerify(
            '#####',
        ),

        'user_id' => $customer->id,
        'ordered_at' => '2026-08-05 10:00:00',
        'subtotal' => '55.00',
        'delivery_fee' => '2.00',
        'total' => '57.00',

        'delivery_type_id' => $references[
            'delivery_type'
        ]->id,

        'payment_method_id' => $references[
            'payment_method'
        ]->id,

        'order_status_id' => $references[
            'pending_status'
        ]->id,

        'address' => 'Av. Principal y Calle 10',
        'delivery_lat' => '-0.8456100',
        'delivery_lng' => '-80.1638900',
        'delivery_maps_url' => 'https://maps.example.test/order',
        'delivery_place_id' => 'place-detail',
        'delivery_reference' => 'Casa blanca',
    ]);
}

it(
    'returns a complete half and half pizza item',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $order = createDetailedCustomerOrder(
            $customer,
        );

        [
            'traditional_category' => $traditionalCategory,
            'special_category' => $specialCategory,
            'medium_size' => $mediumSize,
            'americana' => $americana,
            'supreme' => $supreme,
            'cheese' => $cheese,
            'onion' => $onion,
        ] = myOrderDetailCatalog();

        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'promotion_id' => null,
            'promotion_name' => null,

            'pizza_id' => $americana->id,
            'pizza_name' => $americana->pizza_name,
            'category_name' => $traditionalCategory->category_name,

            'pizza_id_second' => $supreme->id,
            'pizza_name_second' => $supreme->pizza_name,
            'category_name_second' => $specialCategory->category_name,

            'size_id' => $mediumSize->id,
            'size_name' => $mediumSize->size_name,

            'is_half_and_half' => true,
            'quantity' => 2,
            'unit_price' => '16.50',
            'subtotal' => '33.00',
        ]);

        $references = myOrderDetailReferences();

        OrderItemPersonalization::query()->create([
            'order_item_id' => $item->id,
            'order_promotion_item_id' => null,
            'ingredient_id' => $cheese->id,
            'ingredient_name' => $cheese->ingredient_name,

            'personalization_action_id' => $references[
                'extra_action'
            ]->id,

            'applies_to' => 'A',
            'modification_type' => 'extra',
            'extra_price' => '1.50',
        ]);

        OrderItemPersonalization::query()->create([
            'order_item_id' => $item->id,
            'order_promotion_item_id' => null,
            'ingredient_id' => $onion->id,
            'ingredient_name' => $onion->ingredient_name,

            'personalization_action_id' => $references[
                'remove_action'
            ]->id,

            'applies_to' => 'B',
            'modification_type' => 'remove',
            'extra_price' => '0.00',
        ]);

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                "/api/v1/my/orders/{$order->id}",
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.items',
            )
            ->assertJsonPath(
                'data.items.0.id',
                (int) $item->id,
            )
            ->assertJsonPath(
                'data.items.0.item_type',
                'pizza',
            )
            ->assertJsonPath(
                'data.items.0.is_half_and_half',
                true,
            )
            ->assertJsonPath(
                'data.items.0.promotion',
                null,
            )
            ->assertJsonPath(
                'data.items.0.pizza.id',
                (int) $americana->id,
            )
            ->assertJsonPath(
                'data.items.0.pizza.name',
                'Americana',
            )
            ->assertJsonPath(
                'data.items.0.pizza.category',
                $traditionalCategory->category_name,
            )
            ->assertJsonPath(
                'data.items.0.pizza_second.id',
                (int) $supreme->id,
            )
            ->assertJsonPath(
                'data.items.0.pizza_second.name',
                'Suprema',
            )
            ->assertJsonPath(
                'data.items.0.pizza_second.category',
                $specialCategory->category_name,
            )
            ->assertJsonPath(
                'data.items.0.size.id',
                (int) $mediumSize->id,
            )
            ->assertJsonPath(
                'data.items.0.size.name',
                $mediumSize->size_name,
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2,
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                16.5,
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                33,
            )
            ->assertJsonCount(
                2,
                'data.items.0.personalizations',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.ingredient_id',
                (int) $cheese->id,
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.ingredient_name',
                'Queso extra',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.action',
                'extra',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.applies_to',
                'A',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.extra_price',
                1.5,
            )
            ->assertJsonPath(
                'data.items.0.personalizations.1.ingredient_id',
                (int) $onion->id,
            )
            ->assertJsonPath(
                'data.items.0.personalizations.1.ingredient_name',
                'Cebolla',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.1.action',
                'remove',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.1.applies_to',
                'B',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.1.extra_price',
                0,
            )
            ->assertJsonPath(
                'data.items_count',
                2,
            );
    },
);

it(
    'returns the selected pizzas of a promotion item',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $order = createDetailedCustomerOrder(
            $customer,
        );

        [
            'large_size' => $largeSize,
            'hawaiian' => $hawaiian,
            'pepperoni' => $pepperoni,
            'bacon' => $bacon,
            'promotion' => $promotion,
        ] = myOrderDetailCatalog();

        $item = OrderItem::query()->create([
            'order_id' => $order->id,

            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->promotion_name,

            'pizza_id' => null,
            'pizza_name' => null,
            'category_name' => null,

            'pizza_id_second' => null,
            'pizza_name_second' => null,
            'category_name_second' => null,

            'size_id' => $largeSize->id,
            'size_name' => $largeSize->size_name,

            'is_half_and_half' => false,
            'quantity' => 1,
            'unit_price' => '22.00',
            'subtotal' => '22.00',
        ]);

        $firstSelection = OrderPromotionItem::query()->create([
            'order_item_id' => $item->id,
            'pizza_id' => $hawaiian->id,
            'pizza_name' => $hawaiian->pizza_name,
        ]);

        $secondSelection = OrderPromotionItem::query()->create([
            'order_item_id' => $item->id,
            'pizza_id' => $pepperoni->id,
            'pizza_name' => $pepperoni->pizza_name,
        ]);

        $references = myOrderDetailReferences();

        OrderItemPersonalization::query()->create([
            'order_item_id' => $item->id,

            'order_promotion_item_id' => $firstSelection->id,

            'ingredient_id' => $bacon->id,
            'ingredient_name' => $bacon->ingredient_name,

            'personalization_action_id' => $references[
                'extra_action'
            ]->id,

            'applies_to' => 'ALL',
            'modification_type' => 'extra',
            'extra_price' => '2.00',
        ]);

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                "/api/v1/my/orders/{$order->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.item_type',
                'promotion',
            )
            ->assertJsonPath(
                'data.items.0.promotion.id',
                (int) $promotion->id,
            )
            ->assertJsonPath(
                'data.items.0.promotion.name',
                'Combo familiar',
            )
            ->assertJsonPath(
                'data.items.0.pizza',
                null,
            )
            ->assertJsonPath(
                'data.items.0.pizza_second',
                null,
            )
            ->assertJsonPath(
                'data.items.0.size.id',
                (int) $largeSize->id,
            )
            ->assertJsonCount(
                2,
                'data.items.0.selected_pizzas',
            )
            ->assertJsonPath(
                'data.items.0.selected_pizzas.0.id',
                (int) $firstSelection->id,
            )
            ->assertJsonPath(
                'data.items.0.selected_pizzas.0.pizza_id',
                (int) $hawaiian->id,
            )
            ->assertJsonPath(
                'data.items.0.selected_pizzas.0.pizza_name',
                'Hawaiana',
            )
            ->assertJsonPath(
                'data.items.0.selected_pizzas.1.id',
                (int) $secondSelection->id,
            )
            ->assertJsonPath(
                'data.items.0.selected_pizzas.1.pizza_id',
                (int) $pepperoni->id,
            )
            ->assertJsonPath(
                'data.items.0.selected_pizzas.1.pizza_name',
                'Pepperoni',
            )
            ->assertJsonCount(
                1,
                'data.items.0.personalizations',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.order_promotion_item_id',
                (int) $firstSelection->id,
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.ingredient_id',
                (int) $bacon->id,
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.ingredient_name',
                'Tocino',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.action',
                'extra',
            )
            ->assertJsonPath(
                'data.items.0.personalizations.0.extra_price',
                2,
            );
    },
);

it(
    'calculates the detail items count using item quantities',
    function (): void {
        /** @var TestCase $this */
        $customer = User::factory()
            ->customer()
            ->create();

        $order = createDetailedCustomerOrder(
            $customer,
        );

        [
            'traditional_category' => $traditionalCategory,
            'small_size' => $smallSize,
            'medium_size' => $mediumSize,
            'americana' => $americana,
            'promotion' => $promotion,
        ] = myOrderDetailCatalog();

        OrderItem::query()->create([
            'order_id' => $order->id,
            'promotion_id' => null,
            'promotion_name' => null,

            'pizza_id' => $americana->id,
            'pizza_name' => $americana->pizza_name,

            'pizza_id_second' => null,
            'pizza_name_second' => null,

            'size_id' => $smallSize->id,
            'size_name' => $smallSize->size_name,

            'category_name' => $traditionalCategory->category_name,
            'category_name_second' => null,

            'is_half_and_half' => false,
            'quantity' => 2,
            'unit_price' => '8.00',
            'subtotal' => '16.00',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,

            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->promotion_name,

            'pizza_id' => null,
            'pizza_name' => null,

            'pizza_id_second' => null,
            'pizza_name_second' => null,

            'size_id' => $mediumSize->id,
            'size_name' => $mediumSize->size_name,

            'category_name' => null,
            'category_name_second' => null,

            'is_half_and_half' => false,
            'quantity' => 3,
            'unit_price' => '13.00',
            'subtotal' => '39.00',
        ]);

        $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->getJson(
                "/api/v1/my/orders/{$order->id}",
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.items',
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2,
            )
            ->assertJsonPath(
                'data.items.1.quantity',
                3,
            )
            ->assertJsonPath(
                'data.items_count',
                5,
            );
    },
);
