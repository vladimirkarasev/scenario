<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;

final class BrandsProxyHandler extends AutoCrmEndpointHandler
{
    public function requestFields(): iterable
    {
        return [];
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function responseFields(): iterable
    {
        yield ProxyFieldInteger::make('id')->label('ID бренда')->identity();
        yield ProxyFieldString::make('name')->label('Бренд');
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $brands = $this->autoCrm($proxyContext)->brands();

        $items = array_map(static fn (AutoCrmData $brand): array => [
            'id' => $brand->id(),
            'name' => $brand->get('name'),
        ], $brands);

        return ProxyResponse::accepted([
            'request_id' => $proxyContext->requestId(),
            'items' => $items,
        ]);
    }
}
