<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Motorinvest\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;
use Module\Proxy\Gateway\AutoCrm\MotorinvestAutoCrmGateway;
use Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler as BaseModelsProxyHandler;

final class ModelsProxyHandler extends BaseModelsProxyHandler
{
    public function __construct(private readonly MotorinvestAutoCrmGateway $autoCrm) {}

    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $models = $this->autoCrm->models();

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
