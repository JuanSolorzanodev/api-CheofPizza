<?php

namespace App\Events\Operator;

use App\Http\Resources\Api\V1\Operator\OperatorOrderDetailResource;
use App\Http\Resources\Api\V1\Operator\OperatorOrderListResource;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'database';
    public string $queue = 'broadcasts';

    public function __construct(public Order $order)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operator.orders'),
            new PrivateChannel('operator.orders.' . $this->order->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'operator.order.created';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => (int) $this->order->id,
            'summary' => (new OperatorOrderListResource($this->order))->resolve(),
            'detail' => (new OperatorOrderDetailResource($this->order))->resolve(),
        ];
    }
}
