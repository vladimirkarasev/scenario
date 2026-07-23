<?php

declare(strict_types=1);

namespace Module\Schedule\Support;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;

/**
 * Override the injected {@see TemporalTaskQueue} for a single constructor parameter, e.g.
 * `#[TaskQueue('action')] private TemporalTaskQueue $taskQueue` — without it, the
 * container hands out the app-wide singleton (`TEMPORAL_TASK_QUEUE` env). Only needed once
 * a specific service must run on its own Temporal task queue instead of the shared default.
 *
 * `$default` is used only if the app-wide singleton isn't bound (e.g. resolved outside the
 * full app container, such as in an isolated test) — it never overrides a bound singleton.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class TaskQueue implements ContextualAttribute
{
    public function __construct(
        public ?string $name = null,
        public string $default = 'default',
    ) {}

    public static function resolve(self $attribute, Container $container): TemporalTaskQueue
    {
        if ($attribute->name !== null && $attribute->name !== '') {
            return new TemporalTaskQueue($attribute->name);
        }

        if ($container->bound(TemporalTaskQueue::class)) {
            return $container->make(TemporalTaskQueue::class);
        }

        return new TemporalTaskQueue($attribute->default);
    }
}
