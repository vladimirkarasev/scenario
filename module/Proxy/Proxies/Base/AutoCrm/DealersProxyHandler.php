<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\AutoCrm;

use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\ProxyHandler;

class DealersProxyHandler extends ProxyHandler
{
    public function fields(): iterable
    {
        yield ProxyFieldInteger::make('dealer_id')
            ->label('ID дилера')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('dealer_code')
            ->label('Код дилера')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldString::make('dealer_name')
            ->label('Дилер')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldString::make('dealer_marketing_name')
            ->label('Маркетинговое название')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldInteger::make('dealer_city_id')
            ->label('ID города')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('dealer_city')
            ->label('Город')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldString::make('dealer_address')
            ->label('Адрес')
            ->nullable()
            ->rules(['nullable', 'string', 'max:500']);

        yield ProxyFieldString::make('dealer_service_address')
            ->label('Адрес СТО')
            ->nullable()
            ->rules(['nullable', 'string', 'max:500']);

        yield ProxyFieldString::make('dealer_phone')
            ->label('Телефон')
            ->nullable()
            ->rules(['nullable', 'string', 'max:50']);

        yield ProxyFieldInteger::make('dealer_distributor_id')
            ->label('ID дистрибьютора')
            ->nullable()
            ->rules(['nullable', 'integer']);
    }
}
