<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Test;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\ProxyHandler;

final class TestLeadProxyHandler extends ProxyHandler
{
    public function fields(): iterable
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

        yield ProxyFieldString::make('phone')
            ->label('Телефон')
            ->required()
            ->rules(['required', 'string', 'max:30'])
            ->example('+79990000000');

        yield ProxyFieldString::make('email')
            ->label('Email')
            ->nullable()
            ->rules(['nullable', 'email', 'max:255'])
            ->example('test@example.com');

        yield ProxyFieldString::make('comment')
            ->label('Комментарий')
            ->nullable()
            ->rules(['nullable', 'string', 'max:1000'])
            ->example('Тестовая заявка');

        yield ProxyFieldInteger::make('dealer_id')
            ->label('ID дилера')
            ->nullable()
            ->rules(['nullable', 'integer'])
            ->example(1);

        yield ProxyFieldString::make('source')
            ->label('Источник')
            ->source('query.source')
            ->nullable()
            ->default('test');
    }

    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $firstName = is_string($proxyContext->data('first_name')) ? $proxyContext->data('first_name') : '';
        $lastName = is_string($proxyContext->data('last_name')) ? $proxyContext->data('last_name') : '';
        $fullName = trim($firstName.' '.$lastName) ?: 'Без имени';

        return ProxyResponse::accepted([
            'request_id' => $proxyContext->requestId(),
            'lead_id' => random_int(10000, 99999),
            'status' => 'created',
            'message' => 'Тестовая заявка успешно принята',
            'data' => [
                'name' => $fullName,
                'phone' => $proxyContext->data('phone'),
                'email' => $proxyContext->data('email'),
                'dealer_id' => $proxyContext->data('dealer_id'),
                'source' => $proxyContext->data('source', 'test'),
            ],
        ]);
    }
}
