<?php

declare(strict_types=1);

namespace Module\Proxy\Registry\Test;

use Generator;
use Module\Proxy\DTO\ProxyEndpointDefinition;
use Module\Proxy\Proxies\Test\TestEchoProxyHandler;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;

final class TestProxyRegistry
{
    private function __construct()
    {
    }

    /** @return Generator<int, ProxyEndpointDefinition, mixed, void> */
    public static function all(): Generator
    {
        yield new ProxyEndpointDefinition(
            uuid: 'ffffffff-0000-0000-0000-000000000001',
            code: 'test_echo',
            name: 'Test Echo',
            description: 'Тестовый прокси — отражает полученные данные обратно',
            handlerClass: TestEchoProxyHandler::class,
            method: 'GET',
        );

        yield new ProxyEndpointDefinition(
            uuid: 'ffffffff-0000-0000-0000-000000000002',
            code: 'test_lead',
            name: 'Test Lead',
            description: 'Тестовый прокси — принимает заявку и возвращает фейковый ответ CRM',
            handlerClass: TestLeadProxyHandler::class,
            method: 'POST',
        );
    }
}
