<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;

final class ModelsProxyHandler extends AutoCrmEndpointHandler
{
    public function requestFields(): iterable
    {
        return [];
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function responseFields(): iterable
    {
        yield ProxyFieldInteger::make('id')->label('ID модели')->identity();
        yield ProxyFieldString::make('name')->label('Модель');
        yield ProxyFieldInteger::make('alias_id')->label('ID алиаса модели');
        yield ProxyFieldInteger::make('brand_id')->label('ID бренда');
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $models = $this->autoCrm($proxyContext)->models();

        $items = array_map(static fn (AutoCrmData $model): array => [
            'id' => $model->id(),
            'name' => $model->get('name'),
            'alias_id' => $model->get('alias_id'),
            'brand_id' => $model->get('brand_id'),
        ], $models);

        return ProxyResponse::accepted([
            'request_id' => $proxyContext->requestId(),
            'items' => $items,
        ]);
    }
}
