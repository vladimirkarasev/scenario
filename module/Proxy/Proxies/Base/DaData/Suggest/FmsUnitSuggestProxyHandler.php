<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\DaData\Suggest;

use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\Base\DaData\DaDataSuggestEndpointHandler;

final class FmsUnitSuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count): array
    {
        return $gateway->suggestFmsUnit($query, $count);
    }
}
