<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Services;

use Module\Proxy\Gateway\Base\Contracts\ApiGateway;
use Module\Proxy\Gateway\Base\Contracts\ApiMethod;
use Module\Proxy\Gateway\Base\Contracts\ApiTransport;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Psr\Log\LoggerInterface;
use Throwable;

class BaseApiGateway implements ApiGateway
{
    public function __construct(
        protected ApiGatewayConfig $config,
        protected ApiTransport $transport,
        protected MockApiTransport $mockTransport,
        protected LoggerInterface $logger,
    ) {}

    public function send(ApiMethod $method): ApiGatewayResponse
    {
        $transport = $this->config->mock ? $this->mockTransport : $this->transport;

        $this->logger->info('Proxy gateway request started', [
            'gateway' => $this->config->name,
            'method_key' => $method->key(),
            'http_method' => $method->method(),
            'uri' => $method->uri(),
            'mock' => $this->config->mock,
        ]);

        try {
            $response = $transport->send($this->config, $method);
        } catch (Throwable $exception) {
            $this->logger->error('Proxy gateway request failed', [
                'gateway' => $this->config->name,
                'method_key' => $method->key(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->logger->info('Proxy gateway request finished', [
            'gateway' => $this->config->name,
            'method_key' => $method->key(),
            'status_code' => $response->statusCode,
            'mock' => $this->config->mock,
        ]);

        return $response;
    }
}
