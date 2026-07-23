<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Schedule\Support;

use Module\Schedule\Support\TaskQueue;
use Module\Schedule\Support\TemporalTaskQueue;
use Tests\TestCase;

final class TaskQueueTest extends TestCase
{
    public function test_resolve_returns_the_named_queue_when_provided(): void
    {
        $result = TaskQueue::resolve(new TaskQueue('action'), $this->app);

        $this->assertSame('action', $result->value());
    }

    public function test_resolve_falls_back_to_the_container_singleton_without_a_name(): void
    {
        $this->app->instance(TemporalTaskQueue::class, new TemporalTaskQueue('shared-default'));

        $result = TaskQueue::resolve(new TaskQueue(), $this->app);

        $this->assertSame('shared-default', $result->value());
    }

    public function test_resolve_falls_back_to_the_default_value_when_nothing_is_bound(): void
    {
        unset($this->app[TemporalTaskQueue::class]);

        $result = TaskQueue::resolve(new TaskQueue(default: 'action'), $this->app);

        $this->assertSame('action', $result->value());
    }
}
