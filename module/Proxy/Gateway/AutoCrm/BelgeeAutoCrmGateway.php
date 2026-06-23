<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm;

use Module\Proxy\Gateway\Base\Services\ApiGatewayConfigRepository;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Psr\Log\LoggerInterface;

final class BelgeeAutoCrmGateway extends AutoCrmGateway
{
    public function __construct(
        ApiGatewayConfigRepository $configs,
        GuzzleApiTransport $transport,
        MockApiTransport $mockTransport,
        LoggerInterface $logger,
    ) {
        parent::__construct(
            configs: $configs,
            transport: $transport,
            mockTransport: $mockTransport,
            logger: $logger,
            gatewayName: 'belgee_autocrm',
        );
    }
}
