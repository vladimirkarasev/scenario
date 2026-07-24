<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData\Suggest;

use Module\Proxy\DTO\ProxyFieldList;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\DaData\DaDataSuggestEndpointHandler;

final class FioSuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    #[\Override]
    public function fields(): iterable
    {
        yield from parent::fields();

        yield ProxyFieldList::make('gender')
            ->label('Пол')
            ->nullable()
            ->values(['MALE', 'FEMALE', 'UNKNOWN'])
            ->filterable();
    }

    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count, array $options): array
    {
        return $gateway->suggestFio($query, $count, $options);
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function resultFields(): iterable
    {
        yield ProxyFieldString::make('value')->label('Значение');
        yield ProxyFieldString::make('data.surname')->label('Фамилия');
        yield ProxyFieldString::make('data.name')->label('Имя');
        yield ProxyFieldString::make('data.patronymic')->label('Отчество');
        yield ProxyFieldString::make('data.gender')->label('Пол')->filterable('gender');
    }
}
