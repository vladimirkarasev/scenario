<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\ProxyHandler;

final readonly class HandlerResolver
{
    public function __construct(private Container $container) {}

    public function resolve(ProxyEndpoint $endpoint): ProxyHandler
    {
        $class = $endpoint->handler_class;
        $rawNamespace = config('proxy.handler_namespace', 'Module\\Proxy\\Proxies\\');
        $namespace = is_string($rawNamespace) ? $rawNamespace : 'Module\\Proxy\\Proxies\\';
        $allowedHandlers = config('proxy.allowed_handlers', []);

        if ($class === '' || ! class_exists($class)) {
            throw new InvalidArgumentException('Proxy handler class does not exist.');
        }

        $isAllowedNamespace = str_starts_with($class, $namespace);
        $isWhitelisted = in_array($class, is_array($allowedHandlers) ? $allowedHandlers : [], true);

        if (! $isAllowedNamespace && ! $isWhitelisted) {
            throw new InvalidArgumentException('Proxy handler is not allowed.');
        }

        $handler = $this->container->make($class);

        if (! $handler instanceof ProxyHandler) {
            throw new InvalidArgumentException('Proxy handler must extend '.ProxyHandler::class.'.');
        }

        return $handler;
    }
}
