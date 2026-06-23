<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Proxy\Enums\ProxyRequestStatus;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Tests\TestCase;

/**
 * HTTP-тесты лога входящих запросов (только чтение — список и детальная карточка).
 */
final class ProxyRequestLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // GET /api/proxy/requests
    // -------------------------------------------------------------------------

    /**
     * Без фильтров возвращаются все записи в JSON:API ключе data.
     */
    public function test_index_returns_all_requests(): void
    {
        $endpoint = $this->makeEndpoint();
        $this->makeRequest($endpoint);
        $this->makeRequest($endpoint);

        $this->actingAs($this->user)
            ->getJson('/api/proxy/requests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'proxy-requests');
    }

    /**
     * Ответ содержит meta с данными пагинации.
     */
    public function test_index_returns_pagination_meta(): void
    {
        $endpoint = $this->makeEndpoint();
        $this->makeRequest($endpoint);

        $this->actingAs($this->user)
            ->getJson('/api/proxy/requests')
            ->assertOk()
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    /**
     * page[size] ограничивает количество записей на странице.
     */
    public function test_index_paginates_with_page_size(): void
    {
        $endpoint = $this->makeEndpoint();
        $this->makeRequest($endpoint);
        $this->makeRequest($endpoint);
        $this->makeRequest($endpoint);

        $this->actingAs($this->user)
            ->getJson('/api/proxy/requests?page[size]=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    /**
     * filter[endpoint_id]=X — возвращаются только записи нужного эндпоинта.
     */
    public function test_index_filters_by_endpoint_id(): void
    {
        $endpointA = $this->makeEndpoint();
        $endpointB = $this->makeEndpoint();
        $this->makeRequest($endpointA);
        $this->makeRequest($endpointB);

        $this->actingAs($this->user)
            ->getJson("/api/proxy/requests?filter[endpoint_id]={$endpointA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * filter[status]=X — возвращаются только записи нужного статуса.
     */
    public function test_index_filters_by_status(): void
    {
        $endpoint = $this->makeEndpoint();
        $this->makeRequest($endpoint, ProxyRequestStatus::Processed);
        $this->makeRequest($endpoint, ProxyRequestStatus::Failed);

        $this->actingAs($this->user)
            ->getJson('/api/proxy/requests?filter[status]=failed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.status', 'failed');
    }

    /**
     * filter[search] ищет по request_id.
     */
    public function test_index_searches_by_request_id(): void
    {
        $endpoint = $this->makeEndpoint();
        $match = $this->makeRequest($endpoint);
        $this->makeRequest($endpoint);

        $this->actingAs($this->user)
            ->getJson('/api/proxy/requests?filter[search]='.$match->request_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.request_id', $match->request_id);
    }

    /**
     * Список не включает детальные атрибуты (payload, normalized_data, response_body).
     */
    public function test_index_items_exclude_detail_fields(): void
    {
        $endpoint = $this->makeEndpoint();
        $this->makeRequest($endpoint);

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/requests')
            ->assertOk();

        $attributes = $response->json('data.0.attributes');
        $this->assertArrayNotHasKey('payload', $attributes);
        $this->assertArrayNotHasKey('normalized_data', $attributes);
        $this->assertArrayNotHasKey('response_body', $attributes);
    }

    /**
     * Базовые атрибуты присутствуют в каждом элементе списка.
     */
    public function test_index_items_contain_base_fields(): void
    {
        $endpoint = $this->makeEndpoint();
        $this->makeRequest($endpoint);

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/requests')
            ->assertOk();

        $item = $response->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('attributes', $item);
        $this->assertArrayHasKey('request_id', $item['attributes']);
        $this->assertArrayHasKey('status', $item['attributes']);
        $this->assertArrayHasKey('status_label', $item['attributes']);
        $this->assertArrayHasKey('endpoint_id', $item['attributes']);
    }

    /**
     * Неаутентифицированный запрос — 401.
     */
    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/proxy/requests')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // GET /api/proxy/requests/{proxyRequest}
    // -------------------------------------------------------------------------

    /**
     * Детальная карточка включает payload, normalized_data, response_body, received_at, processed_at.
     */
    public function test_show_returns_detail_fields(): void
    {
        $endpoint = $this->makeEndpoint();
        $request = $this->makeRequest($endpoint);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxy/requests/{$request->id}")
            ->assertOk();

        $attributes = $response->json('data.attributes');
        $this->assertArrayHasKey('payload', $attributes);
        $this->assertArrayHasKey('normalized_data', $attributes);
        $this->assertArrayHasKey('response_body', $attributes);
        $this->assertArrayHasKey('received_at', $attributes);
        $this->assertArrayHasKey('processed_at', $attributes);
    }

    /**
     * Корректный ID записи — возвращается нужный лог.
     */
    public function test_show_returns_correct_request(): void
    {
        $endpoint = $this->makeEndpoint();
        $request = $this->makeRequest($endpoint);

        $this->actingAs($this->user)
            ->getJson("/api/proxy/requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string)$request->id)
            ->assertJsonPath('data.attributes.request_id', $request->request_id);
    }

    /**
     * Несуществующий UUID — 404.
     */
    public function test_show_returns_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/proxy/requests/'.Str::uuid())
            ->assertNotFound();
    }

    /**
     * Имя эндпоинта подгружается через связь и попадает в ответ.
     */
    public function test_show_includes_endpoint_name(): void
    {
        $endpoint = $this->makeEndpoint();
        $request = $this->makeRequest($endpoint);

        $this->actingAs($this->user)
            ->getJson("/api/proxy/requests/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.attributes.endpoint', $endpoint->name);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEndpoint(): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Endpoint '.Str::random(4),
            'code' => 'code-'.Str::random(6),
            'is_active' => true,
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }

    private function makeRequest(
        ProxyEndpoint $endpoint,
        ProxyRequestStatus $status = ProxyRequestStatus::Processed
    ): ProxyRequest {
        return ProxyRequest::query()->create([
            'proxy_endpoint_id' => $endpoint->id,
            'request_id' => Str::uuid()->toString(),
            'status' => $status,
            'received_at' => now(),
        ]);
    }
}
