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
     * Схема полей результата (для хендлеров без структурированного результата — пусто).
     *
     * @return iterable<mixed>
     */
    public function resultFields(): iterable
    {
        return [];
    }

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
