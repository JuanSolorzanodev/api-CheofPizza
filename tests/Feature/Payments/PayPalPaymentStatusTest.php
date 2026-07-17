<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartStatus;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Crea un pago PayPal perteneciente al usuario indicado.
 *
 * @return array{
 *     cart: Cart,
 *     payment: Payment
 * }
 */
function createPayPalStatusPayment(
    User $user,
    PaymentStatus $status = PaymentStatus::PENDING,
    string $amount = '5.00',
): array {
    $activeCartStatus = CartStatus::query()
        ->firstOrCreate([
            'status_name' => 'active',
        ]);

    $cart = Cart::query()->create([
        'user_id' =>
            $user->id,

        'cart_status_id' =>
            $activeCartStatus->id,

        'session_id' =>
            null,

        'total' =>
            $amount,
    ]);

    $payment = Payment::query()->create([
        'uuid' =>
            (string) Str::uuid(),

        'idempotency_key' =>
            (string) Str::uuid(),

        'user_id' =>
            $user->id,

        'cart_id' =>
            $cart->id,

        'order_id' =>
            null,

        'provider' =>
            PaymentProvider::PAYPAL,

        'provider_order_id' =>
            'PAYPAL-ORDER-STATUS-' . Str::upper(
                Str::random(12)
            ),

        'provider_capture_id' =>
            null,

        'provider_status' =>
            $status === PaymentStatus::APPROVED
                ? 'APPROVED'
                : 'PAYER_ACTION_REQUIRED',

        'amount' =>
            $amount,

        'currency' =>
            'USD',

        'status' =>
            $status,

        'checkout_context' => [
            'delivery_type' =>
                'pickup',

            'address' =>
                null,

            'delivery_location' =>
                null,
        ],

        'cart_fingerprint' =>
            hash(
                'sha256',
                "paypal-status-{$user->id}-"
                    . Str::random(20)
            ),

        'approved_at' =>
            $status === PaymentStatus::APPROVED
                ? now()
                : null,
    ]);

    return [
        'cart' =>
            $cart,

        'payment' =>
            $payment,
    ];
}

/**
 * Crea un pedido local y lo asocia al pago.
 */
function completePayPalStatusPayment(
    Payment $payment,
    User $user,
): Order {
    $deliveryType = DeliveryType::query()
        ->firstOrCreate([
            'delivery_type_name' =>
                'pickup',
        ]);

    $paymentMethod = PaymentMethod::query()
        ->firstOrCreate(
            [
                'name' =>
                    'card',
            ],
            [
                'description' =>
                    'Tarjeta mediante PayPal',

                'active' =>
                    true,
            ],
        );

    $orderStatus = OrderStatus::query()
        ->firstOrCreate([
            'status_name' =>
                'pending',
        ]);

    $order = Order::query()->create([
        'order_number' =>
            'CH-STATUS-' . Str::upper(
                Str::random(10)
            ),

        'user_id' =>
            $user->id,

        'ordered_at' =>
            now(),

        'total' =>
            $payment->amount,

        'delivery_type_id' =>
            $deliveryType->id,

        'address' =>
            null,

        'payment_method_id' =>
            $paymentMethod->id,

        'order_status_id' =>
            $orderStatus->id,
    ]);

    $captureId =
        'PAYPAL-CAPTURE-STATUS-' . Str::upper(
            Str::random(12)
        );

    /*
     * El pago se encuentra pendiente o aprobado.
     * markAsCompleted() conserva las reglas de transición del dominio.
     */
    $payment->markAsCompleted(
        captureId:
            $captureId,

        providerStatus:
            'COMPLETED',

        providerMetadata: [
            'capture' => [
                'id' =>
                    $captureId,

                'status' =>
                    'COMPLETED',
            ],
        ],
    );

    $payment->forceFill([
        'order_id' =>
            $order->id,
    ])->save();

    return $order;
}

describe(
    'Consulta del estado de un pago PayPal',
    function (): void {
        it(
            'rechaza la consulta cuando el usuario no está autenticado',
            function (): void {
                /** @var TestCase $this */

                $paymentUuid =
                    (string) Str::uuid();

                $response = $this->getJson(
                    "/api/v1/payments/paypal/orders/{$paymentUuid}"
                );

                $response->assertUnauthorized();
            },
        );

        it(
            'no permite consultar un pago perteneciente a otro usuario',
            function (): void {
                /** @var TestCase $this */

                $owner = User::factory()
                    ->customer()
                    ->create();

                $otherCustomer = User::factory()
                    ->customer()
                    ->create();

                [
                    'payment' => $payment,
                ] = createPayPalStatusPayment(
                    user:
                        $owner,
                );

                $response = $this
                    ->actingAs(
                        $otherCustomer,
                        'sanctum',
                    )
                    ->getJson(
                        "/api/v1/payments/paypal/orders/{$payment->uuid}"
                    );

                /*
                 * Respondemos 404 para no revelar la existencia
                 * de pagos pertenecientes a otros usuarios.
                 */
                $response->assertNotFound();
            },
        );

        it(
            'devuelve el estado pendiente del pago perteneciente al usuario',
            function (): void {
                /** @var TestCase $this */

                $user = User::factory()
                    ->customer()
                    ->create();

                [
                    'payment' => $payment,
                ] = createPayPalStatusPayment(
                    user:
                        $user,

                    status:
                        PaymentStatus::PENDING,

                    amount:
                        '5.00',
                );

                $response = $this
                    ->actingAs(
                        $user,
                        'sanctum',
                    )
                    ->getJson(
                        "/api/v1/payments/paypal/orders/{$payment->uuid}"
                    );

                $response
                    ->assertOk()
                    ->assertJsonPath(
                        'message',
                        'Estado del pago PayPal consultado correctamente.',
                    )
                    ->assertJsonPath(
                        'data.payment_id',
                        $payment->uuid,
                    )
                    ->assertJsonPath(
                        'data.paypal_order_id',
                        $payment->provider_order_id,
                    )
                    ->assertJsonPath(
                        'data.paypal_capture_id',
                        null,
                    )
                    ->assertJsonPath(
                        'data.status',
                        PaymentStatus::PENDING->value,
                    )
                    ->assertJsonPath(
                        'data.provider_status',
                        'PAYER_ACTION_REQUIRED',
                    )
                    ->assertJsonPath(
                        'data.amount',
                        '5.00',
                    )
                    ->assertJsonPath(
                        'data.currency',
                        'USD',
                    )
                    ->assertJsonPath(
                        'data.is_terminal',
                        false,
                    )
                    ->assertJsonPath(
                        'data.can_retry_capture',
                        true,
                    )
                    ->assertJsonPath(
                        'data.paid_at',
                        null,
                    )
                    ->assertJsonPath(
                        'data.failed_at',
                        null,
                    )
                    ->assertJsonPath(
                        'data.cancelled_at',
                        null,
                    )
                    ->assertJsonPath(
                        'data.refunded_at',
                        null,
                    )
                    ->assertJsonMissingPath(
                        'data.order',
                    );

                $this->assertDatabaseHas(
                    'payments',
                    [
                        'id' =>
                            $payment->id,

                        'uuid' =>
                            $payment->uuid,

                        'user_id' =>
                            $user->id,

                        'status' =>
                            PaymentStatus::PENDING
                                ->value,

                        'order_id' =>
                            null,
                    ],
                );
            },
        );

        it(
            'devuelve el pedido asociado cuando el pago ya fue completado',
            function (): void {
                /** @var TestCase $this */

                $user = User::factory()
                    ->customer()
                    ->create();

                [
                    'payment' => $payment,
                ] = createPayPalStatusPayment(
                    user:
                        $user,

                    status:
                        PaymentStatus::APPROVED,

                    amount:
                        '12.50',
                );

                $order = completePayPalStatusPayment(
                    payment:
                        $payment,

                    user:
                        $user,
                );

                $payment->refresh();

                $response = $this
                    ->actingAs(
                        $user,
                        'sanctum',
                    )
                    ->getJson(
                        "/api/v1/payments/paypal/orders/{$payment->uuid}"
                    );

                $response
                    ->assertOk()
                    ->assertJsonPath(
                        'message',
                        'Estado del pago PayPal consultado correctamente.',
                    )
                    ->assertJsonPath(
                        'data.payment_id',
                        $payment->uuid,
                    )
                    ->assertJsonPath(
                        'data.paypal_order_id',
                        $payment->provider_order_id,
                    )
                    ->assertJsonPath(
                        'data.paypal_capture_id',
                        $payment->provider_capture_id,
                    )
                    ->assertJsonPath(
                        'data.status',
                        PaymentStatus::COMPLETED->value,
                    )
                    ->assertJsonPath(
                        'data.provider_status',
                        'COMPLETED',
                    )
                    ->assertJsonPath(
                        'data.amount',
                        '12.50',
                    )
                    ->assertJsonPath(
                        'data.currency',
                        'USD',
                    )
                    ->assertJsonPath(
                        'data.is_terminal',
                        true,
                    )
                    ->assertJsonPath(
                        'data.can_retry_capture',
                        false,
                    )
                    ->assertJsonPath(
                        'data.order.id',
                        $order->id,
                    )
                    ->assertJsonPath(
                        'data.order.order_number',
                        $order->order_number,
                    )
                    ->assertJsonPath(
                        'data.order.status',
                        'pending',
                    )
                    ->assertJsonPath(
                        'data.order.total',
                        '12.50',
                    );

                expect(
                    $response->json(
                        'data.paid_at'
                    )
                )->not->toBeNull();

                expect(
                    $response->json(
                        'data.approved_at'
                    )
                )->not->toBeNull();

                expect(
                    $response->json(
                        'data.order.ordered_at'
                    )
                )->not->toBeNull();

                $this->assertDatabaseHas(
                    'payments',
                    [
                        'id' =>
                            $payment->id,

                        'user_id' =>
                            $user->id,

                        'order_id' =>
                            $order->id,

                        'provider_capture_id' =>
                            $payment
                                ->provider_capture_id,

                        'provider_status' =>
                            'COMPLETED',

                        'status' =>
                            PaymentStatus::COMPLETED
                                ->value,
                    ],
                );

                $this->assertDatabaseHas(
                    'orders',
                    [
                        'id' =>
                            $order->id,

                        'user_id' =>
                            $user->id,

                        'order_number' =>
                            $order->order_number,

                        'total' =>
                            '12.50',
                    ],
                );
            },
        );

        it(
            'rechaza un identificador que no tiene formato UUID',
            function (): void {
                /** @var TestCase $this */

                $user = User::factory()
                    ->customer()
                    ->create();

                $response = $this
                    ->actingAs(
                        $user,
                        'sanctum',
                    )
                    ->getJson(
                        '/api/v1/payments/paypal/orders/identificador-invalido'
                    );

                $response->assertNotFound();
            },
        );
    },
);
