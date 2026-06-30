<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;

class BrandsProxyHandler extends AutoCrmEndpointHandler
{
    public function fields(): iterable
    {
        yield ProxyFieldInteger::make('brand_id')
            ->label('ID бренда')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('brand_name')
            ->label('Бренд')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);
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
