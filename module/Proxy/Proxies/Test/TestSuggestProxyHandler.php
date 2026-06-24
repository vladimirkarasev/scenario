<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Test;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\ProxyHandler;

/**
 * Тестовый suggest-proxy для block-поля «Подсказки»: принимает { query } и возвращает
 * отфильтрованный по подстроке список городов (объекты с id/address/region).
 * Каждый вариант целиком сохраняется в ответ опроса.
 */
final class TestSuggestProxyHandler extends ProxyHandler
{
    /** @var list<array{id: string, address: string, region: string}> */
    private const array CITIES = [
        ['id' => '77', 'address' => 'Москва', 'region' => 'Москва'],
        ['id' => '78', 'address' => 'Санкт-Петербург', 'region' => 'Санкт-Петербург'],
        ['id' => '54', 'address' => 'Новосибирск', 'region' => 'Новосибирская область'],
        ['id' => '66', 'address' => 'Екатеринбург', 'region' => 'Свердловская область'],
        ['id' => '52', 'address' => 'Нижний Новгород', 'region' => 'Нижегородская область'],
        ['id' => '16', 'address' => 'Казань', 'region' => 'Татарстан'],
        ['id' => '23', 'address' => 'Краснодар', 'region' => 'Краснодарский край'],
        ['id' => '63', 'address' => 'Самара', 'region' => 'Самарская область'],
    ];

    public function fields(): iterable
    {
        yield ProxyFieldString::make('query')
            ->label('Запрос')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255'])
            ->example('мос');
    }

    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $rawQuery = $proxyContext->data('query');
        $query = mb_strtolower(trim(is_string($rawQuery) ? $rawQuery : ''));

        $items = $query === ''
            ? self::CITIES
            : array_values(array_filter(
                self::CITIES,
                static fn (array $city): bool => str_contains(mb_strtolower((string) $city['address']), $query),
            ));

        return ProxyResponse::ok(['items' => $items]);
    }
}
