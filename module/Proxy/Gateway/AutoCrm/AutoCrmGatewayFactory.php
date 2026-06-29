<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Module\Proxy\Models\ProxyEndpoint;
use Psr\Log\LoggerInterface;

/**
 * Строит AutoCrmGateway с доступами из эндпоинта (БД), а не из статического config/proxy.php.
 */
final readonly class AutoCrmGatewayFactory
{
    public function __construct(
        private GuzzleApiTransport $transport,
        private MockApiTransport $mockTransport,
        private LoggerInterface $logger,
    ) {}

    public function forEndpoint(ProxyEndpoint $endpoint): AutoCrmGateway
    {
        return new AutoCrmGateway(
            config: ApiGatewayConfig::fromEndpoint($endpoint),
            transport: $this->transport,
            mockTransport: $this->mockTransport,
            logger: $this->logger,
        );
    }
}
