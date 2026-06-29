<?php

declare(strict_types=1);

namespace Module\Proxy\Registry;

use Generator;
use Module\Proxy\DTO\ProxyEndpointDefinition;
use Module\Proxy\Proxies\Base\AutoCrm\BrandsProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\DealersProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler;

final class MotorinvestProxyRegistry
{
    private function __construct()
    {
    }

    /** @return Generator<int, ProxyEndpointDefinition, mixed, void> */
    public static function all(): Generator
    {
        yield new ProxyEndpointDefinition(
            uuid: 'c3d4e5f6-0002-0000-0000-000000000001',
            code: 'motorinvest_brands',
            name: 'Motorinvest Бренды',
            description: 'Список брендов Моторинвест из AutoCRM',
            handlerClass: BrandsProxyHandler::class,
            baseUri: self::baseUri(),
            credentials: self::credentials(),
        );

        yield new ProxyEndpointDefinition(
            uuid: 'c3d4e5f6-0002-0000-0000-000000000002',
            code: 'motorinvest_dealers',
            name: 'Motorinvest Дилеры',
            description: 'Список дилеров Моторинвест из AutoCRM',
            handlerClass: DealersProxyHandler::class,
            baseUri: self::baseUri(),
            credentials: self::credentials(),
        );

        yield new ProxyEndpointDefinition(
            uuid: 'c3d4e5f6-0002-0000-0000-000000000003',
            code: 'motorinvest_models',
            name: 'Motorinvest список моделей',
            description: 'Список моделей Моторинвест из AutoCRM',
            handlerClass: ModelsProxyHandler::class,
            baseUri: self::baseUri(),
            credentials: self::credentials(),
        );
    }

    private static function baseUri(): ?string
    {
        $value = config('proxy.endpoint_seeds.motorinvest.base_uri');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function credentials(): array
    {
        $token = config('proxy.endpoint_seeds.motorinvest.bearer_token');

        return is_string($token) && $token !== '' ? ['bearer_token' => $token] : [];
    }
}
