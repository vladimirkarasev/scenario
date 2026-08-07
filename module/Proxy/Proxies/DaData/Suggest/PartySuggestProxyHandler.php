<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData\Suggest;

use Module\Proxy\DTO\ProxyFieldList;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\DaData\DaDataSuggestEndpointHandler;

final class PartySuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    #[\Override]
    public function requestFields(): iterable
    {
        yield from parent::requestFields();

        yield ProxyFieldList::make('type')
            ->label('Тип')
            ->nullable()
            ->values(['INDIVIDUAL', 'LEGAL'])
            ->filterable();
    }

    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count, array $options): array
    {
        return $gateway->suggestParty($query, $count, $options);
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function responseFields(): iterable
    {
        yield ProxyFieldString::make('value')->label('Значение');
        yield ProxyFieldString::make('data.inn')->label('ИНН');
        yield ProxyFieldString::make('data.kpp')->label('КПП');
        yield ProxyFieldString::make('data.ogrn')->label('ОГРН');
        yield ProxyFieldString::make('data.type')->label('Тип')->filterable('type');
        yield ProxyFieldString::make('data.state.status')->label('Статус');
    }
}
