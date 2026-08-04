<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\DeliveryType;
use App\Models\MlDailyFeature;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPromotionItem;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\Pizza;
use App\Models\Promotion;
use App\Models\Size;
use App\Models\User;
use App\Services\MachineLearning\Dataset\DailySalesFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Crea los registros mínimos de catálogo que necesita
 * la consolidación diaria de Machine Learning.
 *
 * @return array{
 *     delivered_status_id: int,
 *     cancelled_status_id: int,
 *     pickup_type_id: int,
 *     delivery_type_id: int,
 *     payment_method_id: int,
 *     size_id: int,
 *     pizza_id: int,
 *     second_pizza_id: int,
 *     promotion_id: int
 * }
 */
function createMlDailySalesCatalog(): array
{
    $deliveredStatus =
        OrderStatus::query()->firstOrCreate([
            'status_name' =>
                'delivered',
        ]);

    $cancelledStatus =
        OrderStatus::query()->firstOrCreate([
            'status_name' =>
                'cancelled',
        ]);

    $pickupType =
        DeliveryType::query()->firstOrCreate([
            'delivery_type_name' =>
                'pickup',
        ]);

    $deliveryType =
        DeliveryType::query()->firstOrCreate([
            'delivery_type_name' =>
                'delivery',
        ]);

    $paymentMethod =
        PaymentMethod::query()->firstOrCreate(
            [
                'name' =>
                    'cash',
            ],
            [
                'description' =>
                    'Pago en efectivo para pruebas de ML',

                'active' =>
                    true,
            ],
        );

    $size =
        Size::query()->create([
            'size_name' =>
                'Mediana',

            'portion' =>
                8,
        ]);

    $category =
        Category::query()->create([
            'category_name' =>
                'Especial',

            'description' =>
                'Categoría para pruebas de Machine Learning',
        ]);

    $pizza =
        Pizza::query()->create([
            'category_id' =>
                $category->id,

            'pizza_name' =>
                'Pizza especial ML',

            'description' =>
                'Pizza para consolidación diaria',

            'image_url' =>
                null,

            'is_visible' =>
                true,
        ]);

    $secondPizza =
        Pizza::query()->create([
            'category_id' =>
                $category->id,

            'pizza_name' =>
                'Segunda pizza ML',

            'description' =>
                'Segunda pizza para promociones',

            'image_url' =>
                null,

            'is_visible' =>
                true,
        ]);

    $promotion =
        Promotion::query()->create([
            'promotion_name' =>
                'Promoción ML',

            'slug' =>
                'promocion-ml-test',

            'description' =>
                'Promoción para pruebas de consolidación',

            'banner_image_url' =>
                null,

            'promotion_type' =>
                Promotion::TYPE_FIXED_COMBO,

            'selection_quantity' =>
                2,

            'promotion_price' =>
                '15.00',

            'starts_at' =>
                '2026-01-01 00:00:00',

            'ends_at' =>
                '2026-12-31 23:59:59',

            'is_active' =>
                true,
        ]);

    return [
        'delivered_status_id' =>
            (int) $deliveredStatus->id,

        'cancelled_status_id' =>
            (int) $cancelledStatus->id,

        'pickup_type_id' =>
            (int) $pickupType->id,

        'delivery_type_id' =>
            (int) $deliveryType->id,

        'payment_method_id' =>
            (int) $paymentMethod->id,

        'size_id' =>
            (int) $size->id,

        'pizza_id' =>
            (int) $pizza->id,

        'second_pizza_id' =>
            (int) $secondPizza->id,

        'promotion_id' =>
            (int) $promotion->id,
    ];
}

/**
 * Crea un pedido con los campos obligatorios de la tabla.
 *
 * @param array<string, int> $catalog
 */
function createMlDailySalesOrder(
    User $customer,
    array $catalog,
    string $orderNumber,
    string $orderedAt,
    int $statusId,
    int $deliveryTypeId,
    string $total,
): Order {
    return Order::query()->create([
        'order_number' =>
            $orderNumber,

        'user_id' =>
            $customer->id,

        'ordered_at' =>
            $orderedAt,

        'subtotal' =>
            $total,

        'delivery_fee' =>
            '0.00',

        'total' =>
            $total,

        'delivery_type_id' =>
            $deliveryTypeId,

        'address' =>
            null,

        'delivery_lat' =>
            null,

        'delivery_lng' =>
            null,

        'delivery_maps_url' =>
            null,

        'delivery_place_id' =>
            null,

        'delivery_reference' =>
            null,

        'payment_method_id' =>
            $catalog['payment_method_id'],

        'order_status_id' =>
            $statusId,
    ]);
}

it(
    'consolida pedidos entregados y excluye pedidos cancelados de las ventas',
    function (): void {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $catalog =
            createMlDailySalesCatalog();

        $deliveredOrder =
            createMlDailySalesOrder(
                customer:
                    $customer,

                catalog:
                    $catalog,

                orderNumber:
                    'ML-TEST-001',

                orderedAt:
                    '2026-08-03 18:00:00',

                statusId:
                    $catalog[
                        'delivered_status_id'
                    ],

                deliveryTypeId:
                    $catalog[
                        'pickup_type_id'
                    ],

                total:
                    '25.00',
            );

        OrderItem::query()->create([
            'order_id' =>
                $deliveredOrder->id,

            'promotion_id' =>
                null,

            'promotion_name' =>
                null,

            'pizza_id' =>
                $catalog['pizza_id'],

            'pizza_name' =>
                'Pizza especial ML',

            'pizza_id_second' =>
                null,

            'pizza_name_second' =>
                null,

            'size_id' =>
                $catalog['size_id'],

            'size_name' =>
                'Mediana',

            'category_name' =>
                'Especial',

            'category_name_second' =>
                null,

            'is_half_and_half' =>
                false,

            'quantity' =>
                2,

            'unit_price' =>
                '12.50',

            'subtotal' =>
                '25.00',
        ]);

        createMlDailySalesOrder(
            customer:
                $customer,

            catalog:
                $catalog,

            orderNumber:
                'ML-TEST-002',

            orderedAt:
                '2026-08-03 19:00:00',

            statusId:
                $catalog[
                    'cancelled_status_id'
                ],

            deliveryTypeId:
                $catalog[
                    'delivery_type_id'
                ],

            total:
                '80.00',
        );

        $feature =
            app(
                DailySalesFeatureService::class,
            )->aggregate(
                '2026-08-03',
            );

        expect(
            $feature->delivered_orders,
        )->toBe(1);

        expect(
            $feature->cancelled_orders,
        )->toBe(1);

        expect(
            $feature->total_pizzas_sold,
        )->toBe(2);

        expect(
            $feature->medium_sales,
        )->toBe(2);

        expect(
            $feature->special_sales,
        )->toBe(2);

        expect(
            $feature->regular_sales,
        )->toBe(2);

        expect(
            $feature->promotion_sales,
        )->toBe(0);

        expect(
            (float) $feature->net_sales,
        )->toBe(25.0);

        expect(
            $feature->pickup_orders,
        )->toBe(1);

        expect(
            $feature->delivery_orders,
        )->toBe(0);
    },
);

it(
    'cuenta pizzas de promociones multiplicadas por la cantidad de paquetes',
    function (): void {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $catalog =
            createMlDailySalesCatalog();

        $order =
            createMlDailySalesOrder(
                customer:
                    $customer,

                catalog:
                    $catalog,

                orderNumber:
                    'ML-PROMO-001',

                orderedAt:
                    '2026-08-03 20:00:00',

                statusId:
                    $catalog[
                        'delivered_status_id'
                    ],

                deliveryTypeId:
                    $catalog[
                        'delivery_type_id'
                    ],

                total:
                    '30.00',
            );

        $orderItem =
            OrderItem::query()->create([
                'order_id' =>
                    $order->id,

                'promotion_id' =>
                    $catalog[
                        'promotion_id'
                    ],

                'promotion_name' =>
                    'Promoción ML',

                'pizza_id' =>
                    null,

                'pizza_name' =>
                    null,

                'pizza_id_second' =>
                    null,

                'pizza_name_second' =>
                    null,

                'size_id' =>
                    $catalog['size_id'],

                'size_name' =>
                    'Mediana',

                'category_name' =>
                    null,

                'category_name_second' =>
                    null,

                'is_half_and_half' =>
                    false,

                'quantity' =>
                    2,

                'unit_price' =>
                    '15.00',

                'subtotal' =>
                    '30.00',
            ]);

        /*
         * Cada paquete contiene dos pizzas.
         * Como se compraron dos paquetes:
         *
         * 2 pizzas × 2 paquetes = 4 pizzas físicas.
         */
        OrderPromotionItem::query()->create([
            'order_item_id' =>
                $orderItem->id,

            'pizza_id' =>
                $catalog['pizza_id'],

            'pizza_name' =>
                'Pizza promocional uno',
        ]);

        OrderPromotionItem::query()->create([
            'order_item_id' =>
                $orderItem->id,

            'pizza_id' =>
                $catalog[
                    'second_pizza_id'
                ],

            'pizza_name' =>
                'Pizza promocional dos',
        ]);

        $feature =
            app(
                DailySalesFeatureService::class,
            )->aggregate(
                '2026-08-03',
            );

        expect(
            $feature->delivered_orders,
        )->toBe(1);

        expect(
            $feature->total_pizzas_sold,
        )->toBe(4);

        expect(
            $feature->medium_sales,
        )->toBe(4);

        expect(
            $feature->promotion_sales,
        )->toBe(4);

        expect(
            $feature->regular_sales,
        )->toBe(0);

        expect(
            (float) $feature->net_sales,
        )->toBe(30.0);

        expect(
            $feature->delivery_orders,
        )->toBe(1);
    },
);

it(
    'cuenta una pizza mitad y mitad como una sola pizza física',
    function (): void {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $catalog =
            createMlDailySalesCatalog();

        $order =
            createMlDailySalesOrder(
                customer:
                    $customer,

                catalog:
                    $catalog,

                orderNumber:
                    'ML-HALF-001',

                orderedAt:
                    '2026-08-03 21:00:00',

                statusId:
                    $catalog[
                        'delivered_status_id'
                    ],

                deliveryTypeId:
                    $catalog[
                        'pickup_type_id'
                    ],

                total:
                    '14.00',
            );

        OrderItem::query()->create([
            'order_id' =>
                $order->id,

            'promotion_id' =>
                null,

            'promotion_name' =>
                null,

            'pizza_id' =>
                $catalog['pizza_id'],

            'pizza_name' =>
                'Pizza mitad uno',

            'pizza_id_second' =>
                $catalog[
                    'second_pizza_id'
                ],

            'pizza_name_second' =>
                'Pizza mitad dos',

            'size_id' =>
                $catalog['size_id'],

            'size_name' =>
                'Mediana',

            'category_name' =>
                'Especial',

            'category_name_second' =>
                'Especial',

            'is_half_and_half' =>
                true,

            'quantity' =>
                1,

            'unit_price' =>
                '14.00',

            'subtotal' =>
                '14.00',
        ]);

        $feature =
            app(
                DailySalesFeatureService::class,
            )->aggregate(
                '2026-08-03',
            );

        expect(
            $feature->total_pizzas_sold,
        )->toBe(1);

        expect(
            $feature->medium_sales,
        )->toBe(1);

        expect(
            $feature->special_sales,
        )->toBe(1);

        expect(
            $feature->regular_sales,
        )->toBe(1);
    },
);

it(
    'es idempotente y actualiza la misma fila al ejecutarse nuevamente',
    function (): void {
        $service =
            app(
                DailySalesFeatureService::class,
            );

        $firstFeature =
            $service->aggregate(
                '2026-08-03',
            );

        $secondFeature =
            $service->aggregate(
                '2026-08-03',
            );

        expect(
            MlDailyFeature::query()->count(),
        )->toBe(1);

        expect(
            $secondFeature->id,
        )->toBe(
            $firstFeature->id,
        );

        expect(
            $secondFeature->date
                ->toDateString(),
        )->toBe(
            '2026-08-03',
        );
    },
);

it(
    'solo consolida pedidos pertenecientes a la fecha solicitada',
    function (): void {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $catalog =
            createMlDailySalesCatalog();

        createMlDailySalesOrder(
            customer:
                $customer,

            catalog:
                $catalog,

            orderNumber:
                'ML-DATE-001',

            orderedAt:
                '2026-08-02 23:59:59',

            statusId:
                $catalog[
                    'delivered_status_id'
                ],

            deliveryTypeId:
                $catalog[
                    'pickup_type_id'
                ],

            total:
                '20.00',
        );

        createMlDailySalesOrder(
            customer:
                $customer,

            catalog:
                $catalog,

            orderNumber:
                'ML-DATE-002',

            orderedAt:
                '2026-08-04 00:00:00',

            statusId:
                $catalog[
                    'delivered_status_id'
                ],

            deliveryTypeId:
                $catalog[
                    'pickup_type_id'
                ],

            total:
                '30.00',
        );

        $feature =
            app(
                DailySalesFeatureService::class,
            )->aggregate(
                '2026-08-03',
            );

        expect(
            $feature->delivered_orders,
        )->toBe(0);

        expect(
            $feature->total_pizzas_sold,
        )->toBe(0);

        expect(
            (float) $feature->net_sales,
        )->toBe(0.0);
    },
);
