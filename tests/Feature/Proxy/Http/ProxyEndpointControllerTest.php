<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Tests\TestCase;

/**
 * HTTP-тесты CRUD-контроллера эндпоинтов.
 * Все роуты защищены auth:sanctum — неаутентифицированный запрос возвращает 401.
 */
final class ProxyEndpointControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // GET /api/proxy/endpoints
    // -------------------------------------------------------------------------

    /**
     * Список эндпоинтов возвращает все записи в ключе items.
     */
    public function test_index_returns_all_endpoints(): void
    {
        $this->makeEndpoint();
        $this->makeEndpoint();

        $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints')
            ->assertOk()
            ->assertJsonCount(2, 'items');
    }

    /**
     * Каждый элемент содержит обязательные поля: id, uuid, name, code, is_active, handler_class.
     */
    public function test_index_items_contain_expected_fields(): void
    {
        $endpoint = $this->makeEndpoint();

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints')
            ->assertOk();

        $item = $response->json('items.0');
        $this->assertSame($endpoint->id, $item['id']);
        $this->assertArrayHasKey('uuid', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('code', $item);
        $this->assertArrayHasKey('is_active', $item);
        $this->assertArrayHasKey('handler_class', $item);
    }

    /**
     * Неаутентифицированный запрос к списку — 401.
     */
    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/proxy/endpoints')
            ->assertUnauthorized();
    }

    /**
     * filter[type]=suggest возвращает только эндпоинты типа suggest.
     */
    public function test_index_filters_by_type(): void
    {
        $this->makeEndpoint();                       // type=webhook (дефолт)
        $suggest = $this->makeEndpoint(type: 'suggest');

        $response = $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints?filter[type]=suggest')
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->assertSame($suggest->id, $response->json('items.0.id'));
        $this->assertSame('suggest', $response->json('items.0.type'));
    }

    // -------------------------------------------------------------------------
    // POST /api/proxy/endpoints
    // -------------------------------------------------------------------------

    /**
     * Валидные данные — создаётся запись, возвращается 201 с item.
     */
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
            ->assertJsonPath('item.name', 'Тестовый эндпоинт')
            ->assertJsonPath('item.code', 'test-endpoint');

        $this->assertDatabaseHas('proxy_endpoints', ['code' => 'test-endpoint']);
    }

    /**
     * Созданный эндпоинт получает uuid автоматически — он присутствует в ответе.
     */
    public function test_store_assigns_uuid_automatically(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [
                'name' => 'Auto UUID',
                'code' => 'auto-uuid',
                'handler_class' => TestLeadProxyHandler::class,
            ]);

        $uuid = $response->json('item.uuid');
        $this->assertNotNull($uuid);
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $uuid), 'uuid должен быть в формате UUID v4');
    }

    /**
     * Отсутствуют обязательные поля name, code, handler_class — 422.
     */
    public function test_store_returns_422_when_required_fields_missing(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/proxy/endpoints', [])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // GET /api/proxy/endpoints/{proxy}
    // -------------------------------------------------------------------------

    /**
     * Существующий эндпоинт — 200 с полными данными в item.
     */
    public function test_show_returns_endpoint_by_id(): void
    {
        $endpoint = $this->makeEndpoint();

        $this->actingAs($this->user)
            ->getJson("/api/proxy/endpoints/{$endpoint->id}")
            ->assertOk()
            ->assertJsonPath('item.id', $endpoint->id)
            ->assertJsonPath('item.name', $endpoint->name);
    }

    /**
     * Несуществующий ID — 404.
     */
    public function test_show_returns_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/proxy/endpoints/99999')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // PUT /api/proxy/endpoints/{proxy}
    // -------------------------------------------------------------------------

    /**
     * Обновление полей — 200, данные сохраняются в БД.
     */
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
            ->assertJsonPath('item.name', 'Обновлённое имя');

        $this->assertDatabaseHas('proxy_endpoints', [
            'id' => $endpoint->id,
            'name' => 'Обновлённое имя',
        ]);
    }

    /**
     * Можно отдельно переключить is_active без изменения других полей.
     */
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
            ->assertJsonPath('item.is_active', false);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/proxy/endpoints/{proxy}
    // -------------------------------------------------------------------------

    /**
     * Удаление существующего эндпоинта — 204, запись исчезает из БД.
     */
    public function test_destroy_deletes_endpoint_and_returns_204(): void
    {
        $endpoint = $this->makeEndpoint();

        $this->actingAs($this->user)
            ->deleteJson("/api/proxy/endpoints/{$endpoint->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('proxy_endpoints', ['id' => $endpoint->id]);
    }

    /**
     * Удаление несуществующего ID — 404.
     */
    public function test_destroy_returns_404_for_nonexistent_id(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/api/proxy/endpoints/99999')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Мок-ответ
    // -------------------------------------------------------------------------

    /**
     * При создании эндпоинта можно сразу задать is_mocked и список mock_responses.
     */
    public function test_store_persists_mock_response_fields(): void
    {
        $variants = [
            [
                'name' => 'Success',
                'status' => 200,
                'body' => ['ok' => true],
                'headers' => ['X-Test' => '1'],
                'match' => ['phone' => '+79990000000'],
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
            ->assertJsonPath('item.is_mocked', true)
            ->assertJsonPath('item.mock_responses.0.status', 200);

        $endpoint = ProxyEndpoint::query()->where('code', 'mocked-endpoint')->firstOrFail();
        $this->assertTrue($endpoint->is_mocked);
        $this->assertSame($variants, $endpoint->mock_responses);
    }

    /**
     * Через update можно переключить is_mocked и заменить mock_responses.
     */
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
            ->assertJsonPath('item.is_mocked', true)
            ->assertJsonPath('item.mock_responses.0.status', 418);

        $endpoint->refresh();
        $this->assertTrue($endpoint->is_mocked);
        $this->assertSame(418, $endpoint->mock_responses[0]['status']);
    }

    /**
     * Невалидный status (не int / вне диапазона) — 422.
     */
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEndpoint(bool $isActive = true, string $type = 'webhook'): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Endpoint '.Str::random(4),
            'code' => 'code-'.Str::random(6),
            'type' => $type,
            'is_active' => $isActive,
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
