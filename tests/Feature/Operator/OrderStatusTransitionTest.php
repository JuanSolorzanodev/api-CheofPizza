<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Events\Customer\OrderUpdated as CustomerOrderUpdated;
use App\Events\Operator\OrderStatusChanged;
use App\Models\DeliveryType;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    /**
     * @var array<string, OrderStatus>
     */
    private array $statuses = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            OrderStatusChanged::class,
            CustomerOrderUpdated::class,
        ]);

        $this->operator = User::factory()
            ->operator()
            ->create();

        foreach (
            [
                'pending',
                'confirmed',
                'preparing',
                'ready',
                'on_the_way',
                'delivered',
                'cancelled',
            ] as $statusName
        ) {
            $this->statuses[$statusName] =
                OrderStatus::query()
                    ->where(
                        'status_name',
                        $statusName,
                    )
                    ->firstOrFail();
        }

        DeliveryType::query()
            ->where(
                'delivery_type_name',
                'pickup',
            )
            ->firstOrFail();

        DeliveryType::query()
            ->where(
                'delivery_type_name',
                'delivery',
            )
            ->firstOrFail();

        PaymentMethod::query()
            ->where(
                'name',
                'cash',
            )
            ->where(
                'active',
                true,
            )
            ->firstOrFail();
    }

    public function test_pickup_order_can_complete_its_valid_flow(): void
    {
        $order = $this->createOrder(
            deliveryType: 'pickup',
        );

        $this->changeStatus(
            order: $order,
            destination: 'confirmed',
            expectedAllowedTransitions: [
                'preparing',
                'cancelled',
            ],
        );

        $this->changeStatus(
            order: $order,
            destination: 'preparing',
            expectedAllowedTransitions: [
                'ready',
                'cancelled',
            ],
        );

        $this->changeStatus(
            order: $order,
            destination: 'ready',
            expectedAllowedTransitions: [
                'delivered',
                'cancelled',
            ],
        );

        $this->changeStatus(
            order: $order,
            destination: 'delivered',
            expectedAllowedTransitions: [],
        );

        $this->assertSame(
            'delivered',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            4,
        );
    }

    public function test_delivery_order_must_pass_through_on_the_way(): void
    {
        $order = $this->createOrder(
            deliveryType: 'delivery',
        );

        $this->changeStatus(
            order: $order,
            destination: 'confirmed',
            expectedAllowedTransitions: [
                'preparing',
                'cancelled',
            ],
        );

        $this->changeStatus(
            order: $order,
            destination: 'preparing',
            expectedAllowedTransitions: [
                'ready',
                'cancelled',
            ],
        );

        $this->changeStatus(
            order: $order,
            destination: 'ready',
            expectedAllowedTransitions: [
                'on_the_way',
                'cancelled',
            ],
        );

        $this->changeStatus(
            order: $order,
            destination: 'on_the_way',
            expectedAllowedTransitions: [
                'delivered',
            ],
        );

        $this->changeStatus(
            order: $order,
            destination: 'delivered',
            expectedAllowedTransitions: [],
        );

        $this->assertSame(
            'delivered',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            5,
        );
    }

    public function test_delivery_order_cannot_go_directly_from_ready_to_delivered(): void
    {
        $order = $this->createOrder(
            deliveryType: 'delivery',
            status: 'ready',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'delivered',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_status',
            ]);

        $this->assertSame(
            'ready',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );

        Event::assertNotDispatched(
            OrderStatusChanged::class,
        );

        Event::assertNotDispatched(
            CustomerOrderUpdated::class,
        );
    }

    public function test_order_cannot_skip_intermediate_states(): void
    {
        $order = $this->createOrder(
            deliveryType: 'pickup',
            status: 'pending',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'ready',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_status',
            ])
            ->assertJsonPath(
                'errors.to_status.0',
                'Transición no permitida: pending → ready.',
            );

        $this->assertSame(
            'pending',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );
    }

    public function test_order_cannot_transition_to_its_current_status(): void
    {
        $order = $this->createOrder(
            deliveryType: 'pickup',
            status: 'confirmed',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'confirmed',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_status',
            ])
            ->assertJsonPath(
                'errors.to_status.0',
                'La orden ya se encuentra en ese estado.',
            );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );
    }

    public function test_delivered_order_cannot_be_modified(): void
    {
        $order = $this->createOrder(
            deliveryType: 'pickup',
            status: 'delivered',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'cancelled',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_status',
            ])
            ->assertJsonPath(
                'errors.to_status.0',
                'No puedes modificar una orden en estado final (delivered).',
            );

        $this->assertSame(
            'delivered',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );
    }

    public function test_cancelled_order_cannot_be_modified(): void
    {
        $order = $this->createOrder(
            deliveryType: 'delivery',
            status: 'cancelled',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'confirmed',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_status',
            ])
            ->assertJsonPath(
                'errors.to_status.0',
                'No puedes modificar una orden en estado final (cancelled).',
            );

        $this->assertSame(
            'cancelled',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );
    }

    public function test_on_the_way_order_cannot_be_cancelled(): void
    {
        $order = $this->createOrder(
            deliveryType: 'delivery',
            status: 'on_the_way',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'cancelled',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_status',
            ])
            ->assertJsonPath(
                'errors.to_status.0',
                'Transición no permitida: on_the_way → cancelled.',
            );

        $this->assertSame(
            'on_the_way',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );
    }

    public function test_valid_transition_creates_history_with_operator_and_note(): void
    {
        $order = $this->createOrder(
            deliveryType: 'pickup',
            status: 'pending',
        );

        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'confirmed',
                    'note' =>
                        'Pedido confirmado por cocina.',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'confirmed',
            )
            ->assertJsonPath(
                'data.allowed_transitions',
                [
                    'preparing',
                    'cancelled',
                ],
            );

        $this->assertDatabaseHas(
            'order_status_changes',
            [
                'order_id' =>
                    $order->id,

                'from_order_status_id' =>
                    $this->statuses['pending']->id,

                'to_order_status_id' =>
                    $this->statuses['confirmed']->id,

                'changed_by_user_id' =>
                    $this->operator->id,

                'note' =>
                    'Pedido confirmado por cocina.',
            ],
        );

        Event::assertDispatched(
            OrderStatusChanged::class,
            function (
                OrderStatusChanged $event,
            ) use ($order): bool {
                return (int) $event
                    ->order
                    ->id === (int) $order->id
                    && $event->fromStatus === 'pending'
                    && $event->toStatus === 'confirmed';
            },
        );

        Event::assertDispatched(
            CustomerOrderUpdated::class,
            function (
                CustomerOrderUpdated $event,
            ) use ($order): bool {
                return (int) $event
                    ->order
                    ->id === (int) $order->id
                    && $event->action === 'status_changed';
            },
        );
    }

    public function test_customer_cannot_access_operator_status_endpoint(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $order = $this->createOrder(
            deliveryType: 'pickup',
            status: 'pending',
        );

        $response = $this
            ->actingAs(
                $customer,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' => 'confirmed',
                ],
            );

        $response->assertForbidden();

        $this->assertSame(
            'pending',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );
    }

    public function test_unauthenticated_user_cannot_change_order_status(): void
    {
        $order = $this->createOrder(
            deliveryType: 'pickup',
            status: 'pending',
        );

        $response = $this->patchJson(
            "/api/v1/operator/orders/{$order->id}/status",
            [
                'to_status' => 'confirmed',
            ],
        );

        $response->assertUnauthorized();

        $this->assertSame(
            'pending',
            $this->currentStatus($order),
        );

        $this->assertDatabaseCount(
            'order_status_changes',
            0,
        );
    }

    /**
     * @param list<string> $expectedAllowedTransitions
     */
    private function changeStatus(
        Order $order,
        string $destination,
        array $expectedAllowedTransitions,
    ): void {
        $response = $this
            ->actingAs(
                $this->operator,
                'sanctum',
            )
            ->patchJson(
                "/api/v1/operator/orders/{$order->id}/status",
                [
                    'to_status' =>
                        $destination,

                    'note' =>
                        "Cambio a {$destination}.",
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (int) $order->id,
            )
            ->assertJsonPath(
                'data.status',
                $destination,
            )
            ->assertJsonPath(
                'data.allowed_transitions',
                $expectedAllowedTransitions,
            );

        $this->assertSame(
            $destination,
            $this->currentStatus($order),
        );
    }

    private function createOrder(
        string $deliveryType,
        string $status = 'pending',
    ): Order {
        $customer = User::factory()
            ->customer()
            ->create();

        $deliveryTypeModel =
            DeliveryType::query()
                ->where(
                    'delivery_type_name',
                    $deliveryType,
                )
                ->firstOrFail();

        $paymentMethod =
            PaymentMethod::query()
                ->where(
                    'name',
                    'cash',
                )
                ->where(
                    'active',
                    true,
                )
                ->firstOrFail();

        return Order::query()->create([
            'order_number' =>
                'TEST-'.strtoupper(
                    fake()
                        ->unique()
                        ->bothify(
                            '########-????',
                        ),
                ),

            'user_id' =>
                $customer->id,

            'ordered_at' =>
                now(),

            'total' =>
                15.50,

            'delivery_type_id' =>
                $deliveryTypeModel->id,

            'address' =>
                $deliveryType === 'delivery'
                    ? 'Dirección de prueba'
                    : null,

            'payment_method_id' =>
                $paymentMethod->id,

            'order_status_id' =>
                $this->statuses[$status]->id,
        ]);
    }

    private function currentStatus(
        Order $order,
    ): string {
        return (string) $order
            ->fresh()
            ?->orderStatus
            ?->status_name;
    }
}
