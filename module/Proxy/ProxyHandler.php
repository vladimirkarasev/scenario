<?php

declare(strict_types=1);

namespace Module\Proxy;

use Module\Proxy\Credentials\ProxyCredential;
use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyResponse;

abstract class ProxyHandler
{
    /** @return iterable<mixed> */
    abstract public function requestFields(): iterable;

    /**
     * Схема полей результата (для хендлеров без структурированного результата — пусто).
     *
     * @return iterable<mixed>
     */
    public function responseFields(): iterable
    {
        return [];
    }

    /**
     * @return class-string<ProxyCredential>|null
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
