<?php

declare(strict_types=1);

namespace Module\Proxy\Events;

use Illuminate\Validation\ValidationException;
use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\Models\ProxyRequest;

final readonly class ProxyRequestRejected
{
    public function __construct(
        public ProxyRequest $proxyRequest,
        public ProxyContext $context,
        public ValidationException $exception,
    ) {}
}
