<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData\Clean;

use Module\Proxy\Gateway\DaData\DaDataCleanGateway;
use Module\Proxy\Gateway\DaData\DTO\DaDataCleanResult;
use Module\Proxy\Proxies\DaData\DaDataCleanEndpointHandler;

final class PhoneCleanProxyHandler extends DaDataCleanEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function clean(DaDataCleanGateway $gateway, string $value): DaDataCleanResult
    {
        return $gateway->cleanPhone($value);
    }
}
