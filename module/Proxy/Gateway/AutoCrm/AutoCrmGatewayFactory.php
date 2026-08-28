<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm;

use Module\Proxy\Gateway\Base\ApiGatewayConfigFactory;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Module\Proxy\Models\ProxyEndpoint;

final readonly class AutoCrmGatewayFactory
{
    public function __construct(
        private GuzzleApiTransport $transport,
        private MockApiTransport $mockTransport,
        private ApiGatewayConfigFactory $configs,
    ) {}

    public function forEndpoint(ProxyEndpoint $endpoint): AutoCrmGateway
    {
        return new AutoCrmGateway(
            config: $this->configs->forEndpoint($endpoint),
            transport: $this->transport,
            mockTransport: $this->mockTransport,
        );
    }
}
