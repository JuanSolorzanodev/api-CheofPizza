<?php

namespace App\Events\Operator;

use App\Http\Resources\Api\V1\Operator\OperatorOrderDetailResource;
use App\Http\Resources\Api\V1\Operator\OperatorOrderListResource;
use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $fromStatus,
        public string $toStatus
    ) {
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
        return 'operator.order.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => (int) $this->order->id,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'summary' => (new OperatorOrderListResource($this->order))->resolve(),
            'detail' => (new OperatorOrderDetailResource($this->order))->resolve(),
        ];
    }
}
