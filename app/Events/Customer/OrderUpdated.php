<?php

namespace App\Events\Customer;

use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'database';
    public string $queue = 'broadcasts';

    public function __construct(
        public Order $order,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customer.orders.' . $this->order->user_id),
            new PrivateChannel('customer.order.' . $this->order->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'customer.order.updated';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing([
            'deliveryType',
            'paymentMethod',
            'orderStatus',
            'orderItems.orderItemPersonalizations',
            'statusChanges.fromStatus',
            'statusChanges.toStatus',
        ]);

        return [
            'action' => $this->action,
            'order_id' => (int) $this->order->id,
            'order' => (new OrderResource($this->order))->resolve(),
        ];
    }
}
