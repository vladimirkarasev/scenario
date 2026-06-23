<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Services;

use Module\Proxy\Gateway\Base\Contracts\ApiGateway;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Psr\Log\LoggerInterface;

final readonly class ApiGatewayFactory
{
    public function __construct(
        private ApiGatewayConfigRepository $configs,
        private GuzzleApiTransport $transport,
        private MockApiTransport $mockTransport,
        private LoggerInterface $logger,
    ) {}

    public function make(string $name = 'default'): ApiGateway
    {
        return new BaseApiGateway(
            config: $this->configs->get($name),
            transport: $this->transport,
            mockTransport: $this->mockTransport,
            logger: $this->logger,
        );
    }

    public function mock(): MockApiTransport
    {
        return $this->mockTransport;
    }
}
