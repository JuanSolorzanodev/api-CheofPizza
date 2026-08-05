<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Customer\OrderUpdated as CustomerOrderUpdated;
use App\Events\Operator\OrderStatusChanged;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class OrderBroadcastContractTest extends TestCase
{
    #[Test]
    public function operator_status_event_broadcasts_after_commit(): void
    {
        $reflection = new ReflectionClass(
            OrderStatusChanged::class,
        );

        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains(
            ShouldBroadcast::class,
            $interfaces,
        );

        $this->assertContains(
            ShouldDispatchAfterCommit::class,
            $interfaces,
        );
    }

    #[Test]
    public function customer_order_event_broadcasts_after_commit(): void
    {
        $reflection = new ReflectionClass(
            CustomerOrderUpdated::class,
        );

        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains(
            ShouldBroadcast::class,
            $interfaces,
        );

        $this->assertContains(
            ShouldDispatchAfterCommit::class,
            $interfaces,
        );
    }

    #[Test]
    public function order_events_use_the_broadcast_queue(): void
    {
        $operatorReflection = new ReflectionClass(
            OrderStatusChanged::class,
        );

        $customerReflection = new ReflectionClass(
            CustomerOrderUpdated::class,
        );

        $this->assertSame(
            'database',
            $operatorReflection
                ->getDefaultProperties()['connection'],
        );

        $this->assertSame(
            'broadcasts',
            $operatorReflection
                ->getDefaultProperties()['queue'],
        );

        $this->assertSame(
            'database',
            $customerReflection
                ->getDefaultProperties()['connection'],
        );

        $this->assertSame(
            'broadcasts',
            $customerReflection
                ->getDefaultProperties()['queue'],
        );
    }
}
