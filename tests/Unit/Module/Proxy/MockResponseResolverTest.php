<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Module\Proxy\Services\MockResponseResolver;
use Tests\TestCase;

/**
 * Юнит-тесты подбора активного мок-варианта.
 */
final class MockResponseResolverTest extends TestCase
{
    use RefreshDatabase;

    private MockResponseResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new MockResponseResolver;
    }

    public function test_returns_null_when_no_variants_defined(): void
    {
        $endpoint = $this->makeEndpoint([]);

        $this->assertNull($this->resolver->resolve($endpoint));
    }

    public function test_returns_active_variant(): void
    {
        $endpoint = $this->makeEndpoint([
            ['name' => 'A', 'status' => 200, 'body' => ['ok' => true], 'is_active' => false],
            ['name' => 'B', 'status' => 422, 'body' => ['ok' => false], 'is_active' => true],
        ]);

        $response = $this->resolver->resolve($endpoint);

        $this->assertNotNull($response);
        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['ok' => false], $response->body);
    }

    public function test_falls_back_to_first_variant_when_none_active(): void
    {
        $endpoint = $this->makeEndpoint([
            ['name' => 'First', 'status' => 202, 'body' => ['first' => true]],
            ['name' => 'Second', 'status' => 500, 'body' => ['second' => true]],
        ]);

        $response = $this->resolver->resolve($endpoint);

        $this->assertNotNull($response);
        $this->assertSame(202, $response->statusCode);
        $this->assertSame(['first' => true], $response->body);
    }

    public function test_adds_x_proxy_mock_header(): void
    {
        $endpoint = $this->makeEndpoint([
            ['status' => 200, 'body' => [], 'is_active' => true],
        ]);

        $response = $this->resolver->resolve($endpoint);

        $this->assertNotNull($response);
        $this->assertSame('1', $response->headers['X-Proxy-Mock'] ?? null);
    }

    /** @param  array<int, array<string, mixed>>  $mocks */
    private function makeEndpoint(array $mocks): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Mocked',
            'code' => 'mocked-'.Str::random(6),
            'is_active' => true,
            'is_mocked' => true,
            'handler_class' => TestLeadProxyHandler::class,
            'mock_responses' => $mocks,
        ]);
    }
}
