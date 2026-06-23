<?php

declare(strict_types=1);

namespace Module\Proxy\Events;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\Models\ProxyRequest;

final readonly class ProxyRequestProcessed
{
    public function __construct(
        public ProxyRequest $proxyRequest,
        public ProxyContext $context,
        public bool $isMocked = false,
    ) {
    }
}
