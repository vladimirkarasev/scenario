<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Module\Proxy\Enums\ProxyEndpointType;

final readonly class ProxyEndpointDefinition
{
    /**
     * @param array<string, mixed> $credentials Стартовые доступы (из env/config); при первом
     *                                           создании шифруются, далее правятся в UI.
     */
    public function __construct(
        public string $uuid,
        public string $code,
        public string $name,
        public string $description,
        public string $handlerClass,
        public string $method = 'POST',
        public ProxyEndpointType $type = ProxyEndpointType::Webhook,
        public ?string $baseUri = null,
        public array $credentials = [],
    ) {}
}
