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
 * Юнит-тесты подбора мок-варианта по нормализованным данным.
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

        $this->assertNull($this->resolver->resolve($endpoint, ['phone' => '+1']));
    }

    public function test_returns_first_matching_variant(): void
    {
        $endpoint = $this->makeEndpoint([
            ['name' => 'A', 'status' => 200, 'body' => ['ok' => true], 'match' => ['phone' => '+1']],
            ['name' => 'B', 'status' => 422, 'body' => ['ok' => false], 'match' => ['phone' => '+2']],
        ]);

        $response = $this->resolver->resolve($endpoint, ['phone' => '+2']);

        $this->assertNotNull($response);
        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['ok' => false], $response->body);
    }

    public function test_falls_back_to_first_variant_without_match(): void
    {
        $endpoint = $this->makeEndpoint([
            ['name' => 'A', 'status' => 422, 'body' => ['err' => 1], 'match' => ['phone' => '+1']],
            ['name' => 'Default', 'status' => 202, 'body' => ['default' => true]],
        ]);

        $response = $this->resolver->resolve($endpoint, ['phone' => '+999']);

        $this->assertNotNull($response);
        $this->assertSame(202, $response->statusCode);
        $this->assertSame(['default' => true], $response->body);
    }

    public function test_returns_first_variant_when_no_match_and_no_default(): void
    {
        $endpoint = $this->makeEndpoint([
            ['name' => 'A', 'status' => 500, 'body' => ['fail' => true], 'match' => ['phone' => '+1']],
        ]);

        $response = $this->resolver->resolve($endpoint, ['phone' => '+999']);

        $this->assertNotNull($response);
        $this->assertSame(500, $response->statusCode);
    }

    public function test_adds_x_proxy_mock_header(): void
    {
        $endpoint = $this->makeEndpoint([
            ['status' => 200, 'body' => []],
        ]);

        $response = $this->resolver->resolve($endpoint, []);

        $this->assertNotNull($response);
        $this->assertSame('1', $response->headers['X-Proxy-Mock'] ?? null);
    }

    public function test_match_requires_all_keys_to_match(): void
    {
        $endpoint = $this->makeEndpoint([
            ['name' => 'A', 'status' => 200, 'match' => ['phone' => '+1', 'email' => 'a@b']],
            ['name' => 'Default', 'status' => 418],
        ]);

        $response = $this->resolver->resolve($endpoint, ['phone' => '+1', 'email' => 'wrong@x']);

        $this->assertNotNull($response);
        $this->assertSame(418, $response->statusCode);
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
