<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Module\Proxy\Models\ProxyConnection;

final readonly class DaDataCleanGatewayFactory
{
    public function __construct(
        private GuzzleApiTransport $transport,
        private MockApiTransport $mockTransport,
    ) {}

    public function forConnection(ProxyConnection $connection, bool $mock = false): DaDataCleanGateway
    {
        return new DaDataCleanGateway(
            config: ApiGatewayConfig::forConnection($connection, DaDataGateway::Clean->value, $mock),
            transport: $this->transport,
            mockTransport: $this->mockTransport,
        );
    }
}
