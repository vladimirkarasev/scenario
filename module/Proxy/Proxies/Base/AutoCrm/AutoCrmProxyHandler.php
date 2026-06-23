<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\AutoCrm;

use Module\Proxy\DTO\ProxyField;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\ProxyHandler;

abstract class AutoCrmProxyHandler extends ProxyHandler
{
    /** @return iterable<ProxyField> */
    protected function leadFields(): iterable
    {
        yield ProxyFieldString::make('first_name')
            ->label('Имя')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255'])
            ->example('Иван');

        yield ProxyFieldString::make('last_name')
            ->label('Фамилия')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255'])
            ->example('Иванов');

        yield ProxyFieldString::make('name')
            ->label('Имя одним полем')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255'])
            ->example('Иван Иванов');

        yield ProxyFieldString::make('phone')
            ->label('Телефон')
            ->required()
            ->rules(['required', 'string', 'max:30'])
            ->example('+79990000000');

        yield ProxyFieldString::make('email')
            ->label('Email')
            ->nullable()
            ->rules(['nullable', 'email', 'max:255'])
            ->example('lead@example.com');

        yield ProxyFieldString::make('source')
            ->label('Источник')
            ->source('query.source')
            ->nullable()
            ->default('webhook');

        yield ProxyFieldInteger::make('city_id')
            ->label('ID города')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('city_name')
            ->label('Город')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldString::make('dealer_code')
            ->label('Код дилера')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldString::make('comment')
            ->label('Комментарий')
            ->nullable()
            ->rules(['nullable', 'string', 'max:2000']);

        yield ProxyFieldString::make('external_request_id')
            ->label('Внешний request id')
            ->source('headers.x-request-id')
            ->nullable();
    }
}
