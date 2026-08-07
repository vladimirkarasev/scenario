<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy\Listeners;

use Module\Proxy\Events\ProxyGatewayRequestFailed;
use Module\Proxy\Events\ProxyGatewayRequestSucceeded;
use Module\Proxy\Listeners\LogProxyGatewayRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class LogProxyGatewayRequestTest extends TestCase
{
    public function test_handle_succeeded_logs_info_with_full_context(): void
    {
        $logger = new class extends NullLogger implements LoggerInterface
        {
            /** @var array<int, array{message: string, context: array<string, mixed>}> */
            public array $infoCalls = [];

            #[\Override]
            public function info($message, array $context = []): void
            {
                $this->infoCalls[] = ['message' => (string) $message, 'context' => $context];
            }
        };

        $event = new ProxyGatewayRequestSucceeded(
            gateway: 'autocrm',
            methodKey: 'CreateLeadMethod',
            httpMethod: 'POST',
            uri: 'leads',
            mock: false,
            requestHeaders: ['Authorization' => '••••••'],
            requestQuery: [],
            requestBody: ['phone' => '+79990000000'],
            statusCode: 202,
            responseHeaders: ['Content-Type' => 'application/json'],
            responseBody: ['lead_id' => 42],
            durationMs: 120,
        );

        (new LogProxyGatewayRequest($logger))->handleSucceeded($event);

        $this->assertCount(1, $logger->infoCalls);
        $this->assertSame('Proxy gateway request succeeded', $logger->infoCalls[0]['message']);
        $context = $logger->infoCalls[0]['context'];
        $this->assertSame('autocrm', $context['gateway']);
        $this->assertSame(202, $context['status_code']);
        $this->assertSame(['lead_id' => 42], $context['response_body']);
        $this->assertSame('••••••', $context['request_headers']['Authorization'] ?? null);
    }

    public function test_handle_failed_logs_error_with_full_context(): void
    {
        $logger = new class extends NullLogger implements LoggerInterface
        {
            /** @var array<int, array{message: string, context: array<string, mixed>}> */
            public array $errorCalls = [];

            #[\Override]
            public function error($message, array $context = []): void
            {
                $this->errorCalls[] = ['message' => (string) $message, 'context' => $context];
            }
        };

        $event = new ProxyGatewayRequestFailed(
            gateway: 'autocrm',
            methodKey: 'CreateLeadMethod',
            httpMethod: 'POST',
            uri: 'leads',
            mock: false,
            requestHeaders: ['Authorization' => '••••••'],
            requestQuery: [],
            requestBody: ['phone' => '+79990000000'],
            error: 'Connection timed out',
            durationMs: 5000,
        );

        (new LogProxyGatewayRequest($logger))->handleFailed($event);

        $this->assertCount(1, $logger->errorCalls);
        $this->assertSame('Proxy gateway request failed', $logger->errorCalls[0]['message']);
        $context = $logger->errorCalls[0]['context'];
        $this->assertSame('Connection timed out', $context['error']);
        $this->assertSame('••••••', $context['request_headers']['Authorization'] ?? null);
    }
}
