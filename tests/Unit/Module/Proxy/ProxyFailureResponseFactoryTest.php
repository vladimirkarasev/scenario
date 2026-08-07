<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayTimeoutException;
use Module\Proxy\Services\ProxyFailureResponseFactory;
use RuntimeException;
use Tests\TestCase;

final class ProxyFailureResponseFactoryTest extends TestCase
{
    public function test_timeout_becomes_safe_gateway_timeout_response(): void
    {
        $response = (new ProxyFailureResponseFactory)->make(
            new ApiGatewayTimeoutException(new RuntimeException('URL and credentials must stay internal')),
        );

        self::assertSame(504, $response->statusCode);
        self::assertSame(
            'Внешний сервис не ответил вовремя. Повторите попытку.',
            $response->body['message'] ?? null,
        );
        $message = $response->body['message'];
        self::assertStringNotContainsString('credentials', $message);
    }

    public function test_unknown_failure_remains_internal_server_error(): void
    {
        $response = (new ProxyFailureResponseFactory)->make(new RuntimeException('boom'));

        self::assertSame(500, $response->statusCode);
        self::assertSame('Proxy processing failed', $response->body['message'] ?? null);
    }
}
