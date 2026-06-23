<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Motorinvest\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;
use Module\Proxy\Gateway\AutoCrm\MotorinvestAutoCrmGateway;
use Module\Proxy\Proxies\Base\AutoCrm\BrandsProxyHandler as BaseBrandsProxyHandler;

final class BrandsProxyHandler extends BaseBrandsProxyHandler
{
    public function __construct(private readonly MotorinvestAutoCrmGateway $autoCrm) {}

    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $brands = $this->autoCrm->brands();

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
