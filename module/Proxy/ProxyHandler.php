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
     * Тип доступа (драйвер {@see \Module\Proxy\Credentials\ProxyCredential}), который требует
     * этот хендлер. null — доступ не нужен (мок/тестовый хендлер без внешнего вызова).
     * Привязать к эндпоинту можно только connection этого типа.
     *
     * @return class-string<\Module\Proxy\Credentials\ProxyCredential>|null
     */
    public function credentialType(): ?string
    {
        return null;
    }

    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        return ProxyResponse::accepted(['request_id' => $proxyContext->requestId()]);
    }
}
