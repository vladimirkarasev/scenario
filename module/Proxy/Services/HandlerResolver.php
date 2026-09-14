<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Container\Attributes\Tag;
use InvalidArgumentException;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\ProxyHandler;

final readonly class HandlerResolver
{
    /** @var array<class-string<ProxyHandler>, ProxyHandler> */
    private array $handlers;

    /** @param iterable<ProxyHandler> $handlers */
    public function __construct(#[Tag('proxy.handlers')] iterable $handlers)
    {
        $resolved = [];
        foreach ($handlers as $handler) {
            $resolved[$handler::class] = $handler;
        }
        $this->handlers = $resolved;
    }

    public function resolve(ProxyEndpoint $endpoint): ProxyHandler
    {
        return $this->resolveClass($endpoint->handler_class);
    }

    public function resolveClass(string $class): ProxyHandler
    {
        if (! isset($this->handlers[$class])) {
            throw new InvalidArgumentException('Proxy handler is not allowed.');
        }

        return $this->handlers[$class];
    }
}
