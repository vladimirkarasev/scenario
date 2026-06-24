<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Module\Proxy\Enums\ProxyEndpointType;

final readonly class ProxyEndpointDefinition
{
    public function __construct(
        public string $uuid,
        public string $code,
        public string $name,
        public string $description,
        public string $handlerClass,
        public string $method = 'POST',
        public ProxyEndpointType $type = ProxyEndpointType::Webhook,
    ) {}
}
