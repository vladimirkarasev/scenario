<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\DaData\Clean;

use Module\Proxy\Gateway\DaData\DaDataCleanGateway;
use Module\Proxy\Gateway\DaData\DTO\DaDataCleanResult;
use Module\Proxy\Proxies\Base\DaData\DaDataCleanEndpointHandler;

final class PassportCleanProxyHandler extends DaDataCleanEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function clean(DaDataCleanGateway $gateway, string $value): DaDataCleanResult
    {
        return $gateway->cleanPassport($value);
    }
}
