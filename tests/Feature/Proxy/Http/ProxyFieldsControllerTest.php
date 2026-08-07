<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Proxy\Models\ProxyEndpoint;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Stubs\Proxy\TestLeadProxyHandler;
use Tests\TestCase;

final class ProxyFieldsControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithProxyProject;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(LoggerInterface::class, new NullLogger());
        $this->user = $this->createProxyUser();
    }

    public function test_returns_fields_for_active_endpoint(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertOk();

        $items = $response->json('data');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function test_fields_contain_expected_keys(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertOk();

        $field = $response->json('data.0.attributes');
        $this->assertArrayHasKey('key', $field);
        $this->assertArrayHasKey('label', $field);
        $this->assertArrayHasKey('type', $field);
        $this->assertArrayHasKey('required', $field);
        $this->assertArrayHasKey('nullable', $field);
    }

    public function test_phone_field_is_present_and_required(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertOk();

        $phoneField = collect($response->json('data'))
            ->pluck('attributes')
            ->firstWhere('key', 'phone');

        $this->assertNotNull($phoneField, 'Поле phone должно присутствовать в списке');
        $this->assertTrue($phoneField['required'], 'Поле phone должно быть обязательным');
    }

    public function test_result_fields_expose_recommended_external_key(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $response = $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/result-fields")
            ->assertOk();

        $idField = collect($response->json('data'))
            ->pluck('attributes')
            ->firstWhere('key', 'id');

        $this->assertIsArray($idField);
        $this->assertTrue($idField['identity']);
    }

    public function test_inactive_endpoint_returns_404(): void
    {
        $endpoint = $this->makeEndpoint(isActive: false);

        $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertNotFound();
    }

    public function test_unknown_uuid_returns_404(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/proxies/'.Str::uuid().'/fields')
            ->assertNotFound();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $endpoint = $this->makeEndpoint(isActive: true);

        $this->getJson("/api/proxies/{$endpoint->uuid}/fields")
            ->assertUnauthorized();
    }

    private function makeEndpoint(bool $isActive = true): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'project_id' => $this->proxyProject->id,
            'uuid' => Str::uuid()->toString(),
            'name' => 'Lead '.Str::random(4),
            'code' => 'lead-'.Str::random(6),
            'is_active' => $isActive,
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }
}
