<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Handlers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Services\Handlers\ProxyRequestActionHandler;
use Module\Proxy\Models\ProxyEndpoint;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Stubs\Proxy\TestLeadProxyHandler;
use Tests\TestCase;

final class ProxyRequestActionHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance(LoggerInterface::class, new NullLogger());
    }

    private function makeEndpoint(): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Test Lead Endpoint',
            'code' => 'test-lead-'.Str::random(8),
            'is_active' => true,
            'is_mocked' => false,
            'handler_class' => TestLeadProxyHandler::class,
        ]);
    }

    public function test_handle_records_sent_request_alongside_response(): void
    {
        $endpoint = $this->makeEndpoint();

        $action = new Action([
            'slug' => 'auto-crm-lead',
            'config' => [
                'endpoint_uuid' => $endpoint->uuid,
                'method' => 'POST',
                'headers' => ['Authorization' => 'Bearer secret-token', 'X-Trace' => 'abc'],
                'query' => ['api_key' => 'super-secret'],
                'payload' => ['phone' => '+79990000000', 'first_name' => 'John'],
            ],
        ]);

        $result = app(ProxyRequestActionHandler::class)->handle($action, []);

        $this->assertSame(ActionRunStatus::Success, $result->status);
        $this->assertSame(202, $result->output['status'] ?? null);
        $this->assertSame('created', $result->output['body']['status'] ?? null);

        $request = $result->output['request'] ?? null;
        $this->assertIsArray($request);
        $this->assertSame('POST', $request['method']);
        $this->assertSame($endpoint->uuid, $request['endpoint_uuid']);
        $this->assertSame(['phone' => '+79990000000', 'first_name' => 'John'], $request['payload']);
        $this->assertSame('••••••', $request['headers']['Authorization'] ?? null);
        $this->assertSame('abc', $request['headers']['X-Trace'] ?? null);
        $this->assertSame('••••••', $request['query']['api_key'] ?? null);
    }

    public function test_handle_records_sent_request_on_failure_too(): void
    {
        $endpoint = $this->makeEndpoint();

        $action = new Action([
            'slug' => 'auto-crm-lead',
            'config' => [
                'endpoint_uuid' => $endpoint->uuid,
                'method' => 'POST',
                'headers' => ['Authorization' => 'Bearer secret-token'],
                // phone отсутствует — TestLeadProxyHandler требует его, получим 422 и ActionResult::failed
                'payload' => ['first_name' => 'John'],
            ],
        ]);

        $result = app(ProxyRequestActionHandler::class)->handle($action, []);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
        $request = $result->output['request'] ?? null;
        $this->assertIsArray($request);
        $this->assertSame('••••••', $request['headers']['Authorization'] ?? null);
    }
}
