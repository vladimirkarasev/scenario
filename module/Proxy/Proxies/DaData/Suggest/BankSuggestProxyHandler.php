<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData\Suggest;

use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\DaData\DaDataSuggestEndpointHandler;

final class BankSuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count, array $options): array
    {
        return $gateway->suggestBank($query, $count, $options);
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function responseFields(): iterable
    {
        yield ProxyFieldString::make('value')->label('Значение');
        yield ProxyFieldString::make('data.bic')->label('БИК');
        yield ProxyFieldString::make('data.swift')->label('SWIFT');
        yield ProxyFieldString::make('data.inn')->label('ИНН');
        yield ProxyFieldString::make('data.name.payment')->label('Наименование');
        yield ProxyFieldString::make('data.correspondent_account')->label('Корр. счёт');
    }
}
