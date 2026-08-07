<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData\Suggest;

use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\DaData\DaDataSuggestEndpointHandler;

final class Okved2SuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count, array $options): array
    {
        return $gateway->suggestOkved2($query, $count, $options);
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function responseFields(): iterable
    {
        yield ProxyFieldString::make('value')->label('Значение');
        yield ProxyFieldString::make('data.code')->label('Код ОКВЭД2');
        yield ProxyFieldString::make('data.name')->label('Название');
    }
}
