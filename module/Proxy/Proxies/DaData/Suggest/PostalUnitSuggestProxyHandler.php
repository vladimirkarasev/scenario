<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData\Suggest;

use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\DaData\DaDataSuggestEndpointHandler;

final class PostalUnitSuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count, array $options): array
    {
        return $gateway->suggestPostalUnit($query, $count, $options);
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function responseFields(): iterable
    {
        yield ProxyFieldString::make('value')->label('Значение');
        yield ProxyFieldString::make('data.postal_code')->label('Индекс');
        yield ProxyFieldString::make('data.region')->label('Регион');
        yield ProxyFieldString::make('data.city')->label('Город');
    }
}
