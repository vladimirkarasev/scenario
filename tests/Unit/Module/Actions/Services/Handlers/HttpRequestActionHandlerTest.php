<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Services\Handlers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Module\Actions\Enums\ActionCredentialType;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionCredential;
use Module\Actions\Services\Handlers\HttpRequestActionHandler;
use Tests\TestCase;

final class HttpRequestActionHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_records_sent_request_with_masked_credentials(): void
    {
        Http::fake([
            'https://crm.example.com/*' => Http::response(['lead_id' => 42], 200),
        ]);

        $credential = ActionCredential::query()->create([
            'name' => 'AutoCRM token',
            'type' => ActionCredentialType::Bearer->value,
            'encrypted_secrets' => ['token' => 'super-secret-token'],
        ]);

        $action = new Action([
            'slug' => 'crm-lead',
            'config' => [
                'method' => 'POST',
                'url' => 'https://crm.example.com/leads',
                'credential_id' => $credential->id,
                'headers' => ['X-Trace' => 'abc'],
                'query' => ['debug' => '1'],
                'body' => ['name' => 'John'],
                'body_type' => 'json',
            ],
        ]);

        $result = app(HttpRequestActionHandler::class)->handle($action, []);

        $this->assertSame(ActionRunStatus::Success, $result->status);
        $this->assertSame(200, $result->output['status'] ?? null);
        $this->assertSame(['lead_id' => 42], $result->output['body'] ?? null);

        $request = $result->output['request'] ?? null;
        $this->assertIsArray($request);
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://crm.example.com/leads', $request['url']);
        $this->assertSame(['name' => 'John'], $request['body']);
        $this->assertSame('abc', $request['headers']['X-Trace'] ?? null);
        $this->assertSame('••••••', $request['headers']['Authorization'] ?? null);
        $this->assertStringNotContainsString('super-secret-token', (string) json_encode($request));
    }

    public function test_handle_records_sent_request_on_failure_too(): void
    {
        Http::fake([
            'https://crm.example.com/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $action = new Action([
            'slug' => 'crm-lead',
            'config' => [
                'method' => 'POST',
                'url' => 'https://crm.example.com/leads',
                'headers' => ['Authorization' => 'Bearer inline-secret'],
                'body' => ['name' => 'John'],
            ],
        ]);

        $result = app(HttpRequestActionHandler::class)->handle($action, []);

        $this->assertSame(ActionRunStatus::Failed, $result->status);
        $request = $result->output['request'] ?? null;
        $this->assertIsArray($request);
        $this->assertSame('••••••', $request['headers']['Authorization'] ?? null);
    }
}
