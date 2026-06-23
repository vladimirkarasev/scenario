<?php

declare(strict_types=1);

namespace Module\Proxy\Registry\Motorinvest;

use Generator;
use Module\Proxy\DTO\ProxyEndpointDefinition;
use Module\Proxy\Proxies\Motorinvest\AutoCrm\BrandsProxyHandler as MotorinvestBrandsProxyHandler;
use Module\Proxy\Proxies\Motorinvest\AutoCrm\DealersProxyHandler as MotorinvestDealersProxyHandler;
use Module\Proxy\Proxies\Motorinvest\AutoCrm\ModelsProxyHandler as MotorinvestModelsProxyHandler;

final class MotorinvestProxyRegistry
{
    private function __construct() {}

    /** @return Generator<int, ProxyEndpointDefinition, mixed, void> */
    public static function all(): Generator
    {
        yield new ProxyEndpointDefinition(
            uuid: 'c3d4e5f6-0002-0000-0000-000000000001',
            code: 'motorinvest_brands',
            name: 'Motorinvest Бренды',
            description: 'Список брендов Моторинвест из AutoCRM',
            handlerClass: MotorinvestBrandsProxyHandler::class,
        );

        yield new ProxyEndpointDefinition(
            uuid: 'c3d4e5f6-0002-0000-0000-000000000002',
            code: 'motorinvest_dealers',
            name: 'Motorinvest Дилеры',
            description: 'Список дилеров Моторинвест из AutoCRM',
            handlerClass: MotorinvestDealersProxyHandler::class,
        );

        yield new ProxyEndpointDefinition(
            uuid: 'c3d4e5f6-0002-0000-0000-000000000003',
            code: 'motorinvest_models',
            name: 'Motorinvest список моделей',
            description: 'Список моделей Моторинвест из AutoCRM',
            handlerClass: MotorinvestModelsProxyHandler::class,
        );
    }
}
