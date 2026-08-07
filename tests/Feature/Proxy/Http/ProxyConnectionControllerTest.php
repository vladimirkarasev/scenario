<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Proxy\Credentials\AutoCrm\AutoCrmCredential;
use Module\Proxy\Credentials\BearerCredential;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Proxies\AutoCrm\ModelsProxyHandler;
use Module\Users\Models\User;
use Tests\Stubs\Proxy\TestLeadProxyHandler;
use Tests\TestCase;

final class ProxyConnectionControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithProxyProject;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createProxyUser();
    }

    public function test_credential_types_returns_drivers_with_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/credential-types')
            ->assertOk();

        $types = array_column($response->json('data'), 'id');
        $this->assertContains(AutoCrmCredential::class, $types);
        $this->assertContains(BearerCredential::class, $types);

        $bearer = collect($response->json('data'))->firstWhere('id', BearerCredential::class);
        $keys = array_column($bearer['attributes']['fields'], 'key');
        $this->assertEqualsCanonicalizing(['base_uri', 'bearer_token'], $keys);
    }

    public function test_store_splits_config_and_encrypts_secret(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/connections', [
                'name' => 'AutoCRM прод',
                'credential_type' => AutoCrmCredential::class,
                'values' => ['base_uri' => 'https://crm.example.com', 'bearer_token' => 'secret-123'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.name', 'AutoCRM прод')
            ->assertJsonPath('data.attributes.config.base_uri', 'https://crm.example.com')
            ->assertJsonPath('data.attributes.secret_filled.bearer_token', true);

        $this->assertArrayNotHasKey('bearer_token', (array) $response->json('data.attributes.config'));

        $raw = DB::table('proxy_connections')->where('name', 'AutoCRM прод')->value('secrets');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('secret-123', $raw);

        $connection = ProxyConnection::query()->where('name', 'AutoCRM прод')->firstOrFail();
        $this->assertSame('secret-123', $connection->values()['bearer_token']);
    }

    public function test_update_with_blank_secret_keeps_existing(): void
    {
        $connection = ProxyConnection::query()->create([
            'project_id' => $this->proxyProject->id,
            'name' => 'CRM',
            'credential_type' => BearerCredential::class,
            'config' => ['base_uri' => 'https://a.example.com'],
            'secrets' => ['bearer_token' => 'original'],
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/proxy/connections/{$connection->id}", [
                'name' => 'CRM ren',
                'credential_type' => BearerCredential::class,
                'values' => ['base_uri' => 'https://b.example.com', 'bearer_token' => ''],
            ])
            ->assertOk();

        $connection->refresh();
        $this->assertSame('original', $connection->values()['bearer_token']);
        $this->assertSame('https://b.example.com', $connection->values()['base_uri']);
    }

    public function test_index_is_scoped_and_filterable_by_type(): void
    {
        ProxyConnection::query()->create(['project_id' => $this->proxyProject->id, 'name' => 'A', 'credential_type' => AutoCrmCredential::class]);
        ProxyConnection::query()->create(['project_id' => $this->proxyProject->id, 'name' => 'B', 'credential_type' => BearerCredential::class]);

        $type = AutoCrmCredential::class;
        $this->actingAs($this->user)
            ->getJson("/api/proxy/connections?filter[credential_type]={$type}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.name', 'A');
    }

    public function test_store_rejects_unknown_credential_type(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/proxy/connections', ['name' => 'X', 'credential_type' => 'App\\Models\\User'])
            ->assertUnprocessable();
    }

    public function test_endpoint_accepts_matching_connection(): void
    {
        $connection = ProxyConnection::query()->create([
            'project_id' => $this->proxyProject->id,
            'name' => 'AutoCRM',
            'credential_type' => AutoCrmCredential::class,
            'config' => ['base_uri' => 'https://crm.example.com'],
            'secrets' => ['bearer_token' => 'tok'],
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Модели',
                'code' => 'models',
                'handler_class' => ModelsProxyHandler::class,
                'connection_id' => $connection->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.connection_id', $connection->id);
    }

    public function test_endpoint_rejects_mismatched_connection_type(): void
    {
        $connection = ProxyConnection::query()->create([
            'project_id' => $this->proxyProject->id,
            'name' => 'Bearer',
            'credential_type' => BearerCredential::class,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Модели',
                'code' => 'models',
                'handler_class' => ModelsProxyHandler::class,
                'connection_id' => $connection->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/connection_id');
    }

    public function test_endpoint_without_credential_handler_rejects_connection(): void
    {
        $connection = ProxyConnection::query()->create([
            'project_id' => $this->proxyProject->id,
            'name' => 'Bearer',
            'credential_type' => BearerCredential::class,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Лид',
                'code' => 'lead',
                'handler_class' => TestLeadProxyHandler::class,
                'connection_id' => $connection->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/connection_id');
    }

    public function test_endpoint_gateway_config_uses_connection(): void
    {
        $connection = ProxyConnection::query()->create([
            'project_id' => $this->proxyProject->id,
            'name' => 'AutoCRM',
            'credential_type' => AutoCrmCredential::class,
            'config' => ['base_uri' => 'https://crm.example.com'],
            'secrets' => ['bearer_token' => 'tok-xyz'],
        ]);

        $endpoint = ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Модели',
            'code' => 'models-'.Str::random(4),
            'handler_class' => ModelsProxyHandler::class,
            'connection_id' => $connection->id,
        ]);

        $config = \Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig::forEndpoint($endpoint->refresh());

        $this->assertSame('https://crm.example.com', $config->baseUri);
        $this->assertSame('bearer', $config->auth['type']);
        $this->assertSame('tok-xyz', $config->auth['token']);
    }
}
