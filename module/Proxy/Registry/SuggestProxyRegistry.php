<?php

declare(strict_types=1);

namespace Module\Proxy\Registry;

use Generator;
use Module\Proxy\DTO\ProxyEndpointDefinition;

final readonly class SuggestProxyRegistry
{
    private function __construct()
    {
    }

    /** @return Generator<int, ProxyEndpointDefinition, mixed, void> */
    public static function all(): Generator
    {
        yield from [];
    }
}