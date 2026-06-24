<?php

declare(strict_types=1);

namespace Module\Proxy\Registry;

use Generator;
use Module\Proxy\DTO\ProxyEndpointDefinition;

final class ProxyRegistry
{
    private function __construct()
    {
    }

    /** @return Generator<int, ProxyEndpointDefinition, mixed, void> */
    public static function all(): Generator
    {
        yield from MotorinvestProxyRegistry::all();
        yield from TestProxyRegistry::all();
        yield from SuggestProxyRegistry::all();
    }
}
