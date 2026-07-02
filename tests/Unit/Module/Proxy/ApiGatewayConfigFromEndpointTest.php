<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Models\ProxyEndpoint;
use Tests\TestCase;

/**
 * Сборка конфига gateway из доступов эндпоинта: тип авторизации выводится
 * из credentials, base_uri и mock — из полей эндпоинта.
 */
final class ApiGatewayConfigFromEndpointTest extends TestCase
{
    public function test_bearer_auth_is_derived_from_token(): void
    {
        $config = ApiGatewayConfig::fromEndpoint($this->endpoint([
            'bearer_token' => 'abc',
        ], baseUri: 'https://api.example.com'));

        $this->assertSame('https://api.example.com', $config->baseUri);
        $this->assertSame('bearer', $config->auth['type']);
        $this->assertSame('abc', $config->auth['token']);
        $this->assertSame('endpoint-code', $config->name);
    }

    public function test_basic_auth_is_derived_from_username(): void
    {
        $config = ApiGatewayConfig::fromEndpoint($this->endpoint([
            'username' => 'user',
            'password' => 'pass',
        ]));

        $this->assertSame('basic', $config->auth['type']);
        $this->assertSame('user', $config->auth['username']);
        $this->assertSame('pass', $config->auth['password']);
    }

    public function test_empty_credentials_yield_none_auth(): void
    {
        $config = ApiGatewayConfig::fromEndpoint($this->endpoint([]));

        $this->assertSame('none', $config->auth['type']);
    }

    public function test_is_mocked_flag_maps_to_mock(): void
    {
        $config = ApiGatewayConfig::fromEndpoint($this->endpoint(['bearer_token' => 'x'], mock: true));

        $this->assertTrue($config->mock);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function endpoint(array $credentials, string $baseUri = '', bool $mock = false): ProxyEndpoint
    {
        $endpoint = new ProxyEndpoint([
            'code' => 'endpoint-code',
            'base_uri' => $baseUri,
            'is_mocked' => $mock,
        ]);
        $endpoint->credentials = $credentials;

        return $endpoint;
    }
}
