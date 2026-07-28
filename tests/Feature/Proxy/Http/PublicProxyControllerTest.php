<?php

declare(strict_types=1);

namespace Tests\Feature\Proxy\Http;

use Module\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Proxy\Enums\ProxyRequestStatus;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;
use Tests\Stubs\Proxy\TestLeadProxyHandler;
use Tests\TestCase;

final class PublicProxyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_valid_post_returns_202_with_request_id_header(): void
    {
        $endpoint = $this->makeEndpoint('POST');

        $response = $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", ['phone' => '+79990000000']);

        $response->assertStatus(202);
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_valid_post_creates_processed_log_record(): void
    {
        $endpoint = $this->makeEndpoint('POST');

        $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", ['phone' => '+79990000000']);

        $log = ProxyRequest::query()->latest('received_at')->first();
        $this->assertSame(ProxyRequestStatus::Processed, $log->status);
        $this->assertNotNull($log->processed_at);
    }

    public function test_missing_required_field_returns_422_and_logs_as_rejected(): void
    {
        $endpoint = $this->makeEndpoint('POST');

        $response = $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", ['email' => 'test@example.com']);

        $response->assertStatus(422);

        $log = ProxyRequest::query()->latest('received_at')->first();
        $this->assertSame(ProxyRequestStatus::Rejected, $log->status);
    }

    public function test_unknown_uuid_returns_404(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/proxies/'.Str::uuid(), ['phone' => '+79990000000'])
            ->assertNotFound();

        $this->assertSame(0, ProxyRequest::query()->count());
    }

    public function test_inactive_endpoint_returns_404(): void
    {
        $endpoint = $this->makeEndpoint('POST', isActive: false);

        $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", ['phone' => '+79990000000'])
            ->assertNotFound();
    }

    public function test_wrong_http_method_returns_405(): void
    {
        $endpoint = $this->makeEndpoint('POST');

        $this->actingAs($this->user)
            ->getJson("/api/proxies/{$endpoint->uuid}")
            ->assertStatus(405);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $endpoint = $this->makeEndpoint('POST');

        $this->postJson("/api/proxies/{$endpoint->uuid}", ['phone' => '+79990000000'])
            ->assertUnauthorized();
    }

    public function test_normalized_data_is_stored_in_log_record(): void
    {
        $endpoint = $this->makeEndpoint('POST');

        $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", [
                'phone' => '+71234567890',
                'email' => 'test@example.com',
            ]);

        $log = ProxyRequest::query()->latest('received_at')->first();
        $this->assertSame('+71234567890', $log->normalized_data['phone'] ?? null);
        $this->assertSame('test@example.com', $log->normalized_data['email'] ?? null);
    }

    public function test_mocked_endpoint_returns_configured_response_instead_of_handler(): void
    {
        $endpoint = $this->makeMockedEndpoint([
            [
                'name' => 'Bad request',
                'status' => 400,
                'body' => ['error' => 'phone_blocked'],
                'is_active' => true,
            ],
            [
                'name' => 'Default',
                'status' => 202,
                'body' => ['mocked' => true],
                'is_active' => false,
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", ['phone' => '+71234567890']);

        $response->assertStatus(400)
            ->assertJson(['error' => 'phone_blocked']);

        $this->assertSame('1', $response->headers->get('X-Proxy-Mock'));
        $this->assertSame(ProxyRequestStatus::Processed, ProxyRequest::query()->latest('received_at')->first()->status);
    }

    public function test_mocked_endpoint_returns_first_variant_when_none_active(): void
    {
        $endpoint = $this->makeMockedEndpoint([
            ['name' => 'Default', 'status' => 202, 'body' => ['mocked' => true]],
            ['name' => 'X', 'status' => 500, 'body' => ['fail' => true]],
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", ['phone' => '+71234567890'])
            ->assertStatus(202)
            ->assertJson(['mocked' => true]);
    }

    public function test_mocked_endpoint_still_validates_payload(): void
    {
        $endpoint = $this->makeMockedEndpoint([
            ['status' => 200, 'body' => ['mocked' => true]],
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/proxies/{$endpoint->uuid}", ['email' => 'no-phone@example.com'])
            ->assertStatus(422);
    }

    private function makeEndpoint(string $method = 'POST', bool $isActive = true): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Lead '.Str::random(4),
            'code' => 'lead-'.Str::random(6),
            'is_active' => $isActive,
            'handler_class' => TestLeadProxyHandler::class,
            'method' => $method,
        ]);
    }

    /** @param  array<int, array<string, mixed>>  $mocks */
    private function makeMockedEndpoint(array $mocks): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Mocked '.Str::random(4),
            'code' => 'mocked-'.Str::random(6),
            'is_active' => true,
            'is_mocked' => true,
            'handler_class' => TestLeadProxyHandler::class,
            'method' => 'POST',
            'mock_responses' => $mocks,
        ]);
    }
}
