<?php

declare(strict_types=1);

namespace Module\Proxy;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyResponse;

abstract class ProxyHandler
{
    /** @return iterable<mixed> */
    abstract public function fields(): iterable;

    /**
     * @return class-string<\Module\Proxy\Credentials\ProxyCredential>|null
     */
    public function credentialType(): ?string
    {
        return null;
    }

    /** HTTP-метод, который принимает публичный endpoint этого обработчика. */
    public function method(): string
    {
        return 'POST';
    }

    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        return ProxyResponse::accepted(['request_id' => $proxyContext->requestId()]);
    }
}
