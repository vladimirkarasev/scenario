<?php

declare(strict_types=1);

namespace Module\Proxy;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyResponse;

abstract class ProxyHandler
{
    /** @return iterable<mixed> */
    abstract public function fields(): iterable;

    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        return ProxyResponse::accepted(['request_id' => $proxyContext->requestId()]);
    }
}
