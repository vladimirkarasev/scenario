<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Tests\TestCase;

final class ProxyEndpointControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithProxyProject;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createProxyUser();
    }

    public function test_index_returns_all_endpoints(): void
    {
        $this->makeEndpoint();
        $this->makeEndpoint();

        $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_items_contain_expected_fields(): void
    {
        $endpoint = $this->makeEndpoint();

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints')
            ->assertOk();

        $item = $response->json('data.0');
        $this->assertSame((string) $endpoint->id, $item['id']);
        $attrs = $item['attributes'];
        $this->assertArrayHasKey('uuid', $attrs);
        $this->assertArrayHasKey('name', $attrs);
        $this->assertArrayHasKey('code', $attrs);
        $this->assertArrayHasKey('is_active', $attrs);
        $this->assertArrayHasKey('handler_class', $attrs);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/proxy/endpoints')
            ->assertUnauthorized();
    }

    public function test_index_filters_by_type(): void
    {
        $this->makeEndpoint();
        $suggest = $this->makeEndpoint(type: 'suggest');

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints?filter[type]=suggest')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame((string) $suggest->id, $response->json('data.0.id'));
        $this->assertSame('suggest', $response->json('data.0.attributes.type'));
    }

    public function test_index_uses_json_api_media_type_meta_and_sparse_fields(): void
    {
        $this->makeEndpoint();

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints?fields[proxy-endpoints]=name,code')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.api+json')
            ->assertJsonStructure(['meta' => ['timestamp', 'requestId']]);

        $this->assertSame(
            ['name', 'code'],
            array_keys($response->json('data.0.attributes')),
        );
    }

    public function test_store_creates_endpoint_and_returns_201(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Тестовый эндпоинт',
                'code' => 'test-endpoint',
                'handler_class' => TestLeadProxyHandler::class,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.attributes.name', 'Тестовый эндпоинт')
            ->assertJsonPath('data.attributes.code', 'test-endpoint');

        $this->assertDatabaseHas('proxy_endpoints', ['code' => 'test-endpoint']);
    }

    public function test_store_assigns_uuid_automatically(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Auto UUID',
                'code' => 'auto-uuid',
                'handler_class' => TestLeadProxyHandler::class,
            ]);

        $uuid = $response->json('data.attributes.uuid');
        $this->assertNotNull($uuid);
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $uuid), 'uuid должен быть в формате UUID v4');
    }

    public function test_store_returns_422_when_required_fields_missing(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [])
            ->assertUnprocessable();
    }

    public function test_show_returns_endpoint_by_id(): void
    {
        $endpoint = $this->makeEndpoint();

        $this->actingAs($this->user)
            ->getJson("/api/proxy/endpoints/{$endpoint->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $endpoint->id)
            ->assertJsonPath('data.attributes.name', $endpoint->name);
    }

    public function test_show_returns_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints/99999')
            ->assertNotFound();
    }

    public function test_update_persists_new_values(): void
    {
        $endpoint = $this->makeEndpoint();

        $this->actingAs($this->user)
            ->putJson("/api/proxy/endpoints/{$endpoint->id}", [
                'name' => 'Обновлённое имя',
                'code' => 'updated-code',
                'handler_class' => TestLeadProxyHandler::class,
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Обновлённое имя');

        $this->assertDatabaseHas('proxy_endpoints', [
            'id' => $endpoint->id,
            'name' => 'Обновлённое имя',
        ]);
    }

    public function test_update_can_deactivate_endpoint(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $this->actingAs($this->user)
            ->putJson("/api/proxy/endpoints/{$endpoint->id}", [
                'name' => $endpoint->name,
                'code' => $endpoint->code,
                'handler_class' => $endpoint->handler_class,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.is_active', false);
    }

    public function test_destroy_deletes_endpoint_and_returns_204(): void
    {
        $endpoint = $this->makeEndpoint();

        $this->actingAs($this->user)
            ->deleteJson("/api/proxy/endpoints/{$endpoint->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('proxy_endpoints', ['id' => $endpoint->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/api/proxy/endpoints/99999')
            ->assertNotFound();
    }

    public function test_store_persists_mock_response_fields(): void
    {
        $variants = [
            [
                'name' => 'Success',
                'status' => 200,
                'body' => ['ok' => true],
                'headers' => ['X-Test' => '1'],
                'is_active' => true,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Mocked',
                'code' => 'mocked-endpoint',
                'handler_class' => TestLeadProxyHandler::class,
                'is_mocked' => true,
                'mock_responses' => $variants,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.attributes.is_mocked', true)
            ->assertJsonPath('data.attributes.mock_responses.0.status', 200);

        $endpoint = ProxyEndpoint::query()->where('code', 'mocked-endpoint')->firstOrFail();
        $this->assertTrue($endpoint->is_mocked);
        $this->assertSame($variants, $endpoint->mock_responses);
    }

    public function test_update_can_change_mock_response_fields(): void
    {
        $endpoint = $this->makeEndpoint();

        $this->actingAs($this->user)
            ->putJson("/api/proxy/endpoints/{$endpoint->id}", [
                'name' => $endpoint->name,
                'code' => $endpoint->code,
                'handler_class' => $endpoint->handler_class,
                'is_mocked' => true,
                'mock_responses' => [
                    ['name' => 'Default', 'status' => 418, 'body' => ['teapot' => true]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.attributes.is_mocked', true)
            ->assertJsonPath('data.attributes.mock_responses.0.status', 418);

        $endpoint->refresh();
        $this->assertTrue($endpoint->is_mocked);
        $this->assertSame(418, $endpoint->mock_responses[0]['status']);
    }

    public function test_store_rejects_invalid_mock_status(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Bad',
                'code' => 'bad-mock',
                'handler_class' => TestLeadProxyHandler::class,
                'mock_responses' => [
                    ['status' => 999, 'body' => []],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_store_persists_credentials_encrypted_and_masks_secret(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Интеграция AutoCRM',
                'code' => 'autocrm-models',
                'handler_class' => \Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler::class,
                'credentials' => [
                    'base_uri' => 'https://crm.example.com',
                    'bearer_token' => 'secret-token-123',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.attributes.base_uri', 'https://crm.example.com')
            ->assertJsonPath('data.attributes.credentials.bearer_token', null)
            ->assertJsonPath('data.attributes.secret_filled.bearer_token', true);

        $this->assertStringContainsString('/api/proxies/', (string) $response->json('data.attributes.receive_url'));

        $raw = \Illuminate\Support\Facades\DB::table('proxy_endpoints')
            ->where('code', 'autocrm-models')->value('credentials');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('secret-token-123', $raw);

        $endpoint = ProxyEndpoint::query()->where('code', 'autocrm-models')->firstOrFail();
        $this->assertSame('secret-token-123', $endpoint->credentials['bearer_token']);
    }

    public function test_update_with_blank_secret_preserves_stored_token(): void
    {
        $endpoint = ProxyEndpoint::query()->create([
            'project_id' => $this->proxyProject->id,
            'uuid' => Str::uuid()->toString(),
            'name' => 'AutoCRM', 'code' => 'autocrm-1',
            'handler_class' => \Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler::class,
            'credentials' => ['bearer_token' => 'original-token'],
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/proxy/endpoints/{$endpoint->id}", [
                'name' => 'AutoCRM ren', 'code' => 'autocrm-1',
                'handler_class' => \Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler::class,
                'credentials' => ['base_uri' => 'https://x.example.com', 'bearer_token' => ''],
            ])
            ->assertOk();

        $endpoint->refresh();
        $this->assertSame('original-token', $endpoint->credentials['bearer_token']);
    }

    public function test_store_rejects_handler_class_not_in_catalog(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Bad', 'code' => 'bad-handler',
                'handler_class' => 'App\\Models\\User',
            ])
            ->assertUnprocessable();
    }

    public function test_handlers_catalog_is_returned(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/handlers')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['type', 'id', 'attributes' => ['label', 'group']]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $classes = array_column($response->json('data'), 'id');
        $this->assertContains(\Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler::class, $classes);
    }

    public function test_handlers_catalog_paginates_and_searches(): void
    {
        $page1 = $this->actingAs($this->user)
            ->getJson('/api/proxy/handlers?page[number]=1&page[size]=2')
            ->assertOk();
        $this->assertCount(2, $page1->json('data'));
        $this->assertSame(2, $page1->json('meta.per_page'));
        $this->assertGreaterThanOrEqual(2, $page1->json('meta.total'));

        $search = $this->actingAs($this->user)
            ->getJson('/api/proxy/handlers?filter[search]=AutoCRM')
            ->assertOk();
        $groups = array_column(array_column($search->json('data'), 'attributes'), 'group');
        $this->assertNotEmpty($groups);
        $this->assertSame(['AutoCRM'], array_values(array_unique($groups)));
    }

    private function makeEndpoint(bool $isActive = true, string $type = 'webhook'): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'project_id' => $this->proxyProject->id,
            'uuid' => Str::uuid()->toString(),
            'name' => 'Endpoint '.Str::random(4),
            'code' => 'code-'.Str::random(6),
            'type' => $type,
            'is_active' => $isActive,
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
