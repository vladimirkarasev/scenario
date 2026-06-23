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
 * HTTP-тесты контроллера публичных полей эндпоинта.
 * Роут: GET /api/proxies/{uuid}/fields
 *
 * Возвращает описание полей handler-а (label, type, required и т.д.) для внешних систем.
 * Работает только для активных эндпоинтов.
 */
final class ProxyFieldsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Успешные кейсы
    // -------------------------------------------------------------------------

    /**
     * Активный эндпоинт — 200, items содержит поля handler-а.
     */
    public function test_returns_fields_for_active_endpoint(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertOk();

        $items = $response->json('items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    /**
     * Каждое поле содержит ключи: key, label, type, required, nullable.
     */
    public function test_fields_contain_expected_keys(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertOk();

        $field = $response->json('items.0');
        $this->assertArrayHasKey('key', $field);
        $this->assertArrayHasKey('label', $field);
        $this->assertArrayHasKey('type', $field);
        $this->assertArrayHasKey('required', $field);
        $this->assertArrayHasKey('nullable', $field);
    }

    /**
     * TestLeadProxyHandler объявляет поле phone как required — оно должно быть в списке.
     */
    public function test_phone_field_is_present_and_required(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertOk();

        $phoneField = collect($response->json('items'))
            ->firstWhere('key', 'phone');

        $this->assertNotNull($phoneField, 'Поле phone должно присутствовать в списке');
        $this->assertTrue($phoneField['required'], 'Поле phone должно быть обязательным');
    }

    // -------------------------------------------------------------------------
    // Ошибки
    // -------------------------------------------------------------------------

    /**
     * Неактивный эндпоинт — 404 (firstOrFail возвращает только активные).
     */
    public function test_inactive_endpoint_returns_404(): void
    {
        $endpoint = $this->makeEndpoint(isActive: false);

        $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertNotFound();
    }

    /**
     * Несуществующий UUID — 404.
     */
    public function test_unknown_uuid_returns_404(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/proxies/'.Str::uuid().'/fields')
            ->assertNotFound();
    }

    /**
     * Неаутентифицированный запрос — 401.
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $this->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEndpoint(bool $isActive = true): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Lead '.Str::random(4),
            'code' => 'lead-'.Str::random(6),
            'is_active' => $isActive,
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
