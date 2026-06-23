<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\ProxyHandler;

final class StubProxyHandler extends ProxyHandler
{
    /** @param  array<string, mixed>  $responseBody */
    public function __construct(private readonly array $responseBody = [])
    {
    }

    public function fields(): iterable
    {
        return [];
    }

    public function handle(ProxyContext $context): ProxyResponse
    {
        return ProxyResponse::ok($this->responseBody);
    }
}
