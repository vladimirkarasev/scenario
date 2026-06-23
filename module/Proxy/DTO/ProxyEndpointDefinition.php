<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final readonly class ProxyEndpointDefinition
{
    public function __construct(
        public string $uuid,
        public string $code,
        public string $name,
        public string $description,
        public string $handlerClass,
        public string $method = 'POST',
    ) {
    }
}
