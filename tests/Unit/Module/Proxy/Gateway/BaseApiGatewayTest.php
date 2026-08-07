<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy\Gateway;

use Illuminate\Support\Facades\Event;
use Module\Proxy\Events\ProxyGatewayRequestFailed;
use Module\Proxy\Events\ProxyGatewayRequestSucceeded;
use Module\Proxy\Gateway\Base\Contracts\ApiMethod;
use Module\Proxy\Gateway\Base\Contracts\ApiTransport;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayException;
use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;
use Module\Proxy\Gateway\Base\Services\BaseApiGateway;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Tests\TestCase;

final class BaseApiGatewayTest extends TestCase
{
    public function test_send_dispatches_succeeded_event_with_masked_headers(): void
    {
        Event::fake();

        $config = new ApiGatewayConfig(name: 'autocrm', baseUri: 'https://autocrm.example.com');
        $transport = $this->fakeTransport(new ApiGatewayResponse(
            statusCode: 200,
            body: ['lead_id' => 42],
            headers: ['Content-Type' => 'application/json'],
        ));

        $method = $this->methodWithHeaders(['Authorization' => 'Bearer secret-token', 'X-Trace' => 'abc']);

        $gateway = new BaseApiGateway($config, $transport, new MockApiTransport);
        $response = $gateway->send($method);

        $this->assertSame(200, $response->statusCode);

        Event::assertDispatched(ProxyGatewayRequestSucceeded::class, function (ProxyGatewayRequestSucceeded $event) {
            $this->assertSame('autocrm', $event->gateway);
            $this->assertSame(200, $event->statusCode);
            $this->assertSame('••••••', $event->requestHeaders['Authorization'] ?? null);
            $this->assertSame('abc', $event->requestHeaders['X-Trace'] ?? null);
            $this->assertSame(['lead_id' => 42], $event->responseBody);

            return true;
        });

        Event::assertNotDispatched(ProxyGatewayRequestFailed::class);
    }

    public function test_send_dispatches_failed_event_when_transport_throws(): void
    {
        Event::fake();

        $config = new ApiGatewayConfig(name: 'autocrm', baseUri: 'https://autocrm.example.com');
        $transport = $this->throwingTransport();
        $method = $this->methodWithHeaders(['Authorization' => 'Bearer secret-token']);

        $gateway = new BaseApiGateway($config, $transport, new MockApiTransport);

        $this->expectException(ApiGatewayException::class);

        try {
            $gateway->send($method);
        } finally {
            Event::assertDispatched(ProxyGatewayRequestFailed::class, function (ProxyGatewayRequestFailed $event) {
                $this->assertSame('••••••', $event->requestHeaders['Authorization'] ?? null);
                $this->assertSame('boom', $event->error);

                return true;
            });

            Event::assertNotDispatched(ProxyGatewayRequestSucceeded::class);
        }
    }

    /** @param  array<string, mixed>  $headers */
    private function methodWithHeaders(array $headers): AbstractApiMethod
    {
        return new readonly class($headers) extends AbstractApiMethod
        {
            public function __construct(private array $requestHeaders) {}

            public function method(): string
            {
                return 'POST';
            }

            public function uri(): string
            {
                return 'leads';
            }

            /** @return array<string, mixed> */
            #[\Override]
            public function headers(): array
            {
                return $this->requestHeaders;
            }
        };
    }

    private function fakeTransport(ApiGatewayResponse $response): ApiTransport
    {
        return new class($response) implements ApiTransport
        {
            public function __construct(private readonly ApiGatewayResponse $response) {}

            public function send(ApiGatewayConfig $config, ApiMethod $method): ApiGatewayResponse
            {
                return $this->response;
            }
        };
    }

    private function throwingTransport(): ApiTransport
    {
        return new class implements ApiTransport
        {
            public function send(ApiGatewayConfig $config, ApiMethod $method): ApiGatewayResponse
            {
                throw new ApiGatewayException('boom');
            }
        };
    }
}
