<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData;

use Module\Proxy\Gateway\Base\ApiGatewayConfigFactory;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Module\Proxy\Models\ProxyConnection;

final readonly class DaDataSuggestGatewayFactory
{
    public function __construct(
        private GuzzleApiTransport $transport,
        private MockApiTransport $mockTransport,
        private ApiGatewayConfigFactory $configs,
    ) {}

    public function forConnection(ProxyConnection $connection, bool $mock = false): DaDataSuggestGateway
    {
        return new DaDataSuggestGateway(
            config: $this->configs->forConnection($connection, DaDataGateway::Suggest->value, $mock),
            transport: $this->transport,
            mockTransport: $this->mockTransport,
        );
    }
}
