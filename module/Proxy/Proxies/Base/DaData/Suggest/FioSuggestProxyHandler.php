<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\DaData\Suggest;

use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\Base\DaData\DaDataSuggestEndpointHandler;

final class FioSuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count): array
    {
        return $gateway->suggestFio($query, $count);
    }
}
