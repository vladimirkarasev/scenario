<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData\Suggest;

use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Proxies\DaData\DaDataSuggestEndpointHandler;

final class AddressSuggestProxyHandler extends DaDataSuggestEndpointHandler
{
    /** @throws \Throwable */
    #[\Override]
    protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count, array $options): array
    {
        return $gateway->suggestAddress($query, $count, $options);
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function resultFields(): iterable
    {
        yield ProxyFieldString::make('value')->label('Значение');
        yield ProxyFieldString::make('unrestricted_value')->label('Значение без ограничений');
        yield ProxyFieldString::make('data.postal_code')->label('Индекс');
        yield ProxyFieldString::make('data.country')->label('Страна');
        yield ProxyFieldString::make('data.region_with_type')->label('Регион');
        yield ProxyFieldString::make('data.city_with_type')->label('Город');
        yield ProxyFieldString::make('data.street_with_type')->label('Улица');
        yield ProxyFieldString::make('data.house')->label('Дом');
        yield ProxyFieldString::make('data.flat')->label('Квартира');
        yield ProxyFieldString::make('data.geo_lat')->label('Широта');
        yield ProxyFieldString::make('data.geo_lon')->label('Долгота');
        yield ProxyFieldString::make('data.fias_id')->label('ФИАС ID');
        yield ProxyFieldString::make('data.kladr_id')->label('КЛАДР ID');
    }
}
