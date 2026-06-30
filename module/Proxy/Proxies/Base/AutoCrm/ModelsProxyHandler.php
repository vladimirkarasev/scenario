<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;

class ModelsProxyHandler extends AutoCrmEndpointHandler
{
    public function fields(): iterable
    {
        yield ProxyFieldInteger::make('model_id')
            ->label('ID модели')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('model_name')
            ->label('Модель')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldInteger::make('model_alias_id')
            ->label('ID алиаса модели')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldInteger::make('brand_id')
            ->label('ID бренда')
            ->nullable()
            ->rules(['nullable', 'integer']);
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
