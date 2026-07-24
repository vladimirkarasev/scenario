<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\DTO\HandlerDefinition;
use Module\Proxy\DTO\HandlerGroupDefinition;
use Module\Proxy\Proxies\Base\AutoCrm\BrandsProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\DealersProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler;
use Module\Proxy\Proxies\DaData\Clean\AddressCleanProxyHandler;
use Module\Proxy\Proxies\DaData\Clean\EmailCleanProxyHandler;
use Module\Proxy\Proxies\DaData\Clean\NameCleanProxyHandler;
use Module\Proxy\Proxies\DaData\Clean\PassportCleanProxyHandler;
use Module\Proxy\Proxies\DaData\Clean\PhoneCleanProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\AddressSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\BankSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\CarBrandSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\CountrySuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\CurrencySuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\EmailSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\FioSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\FmsUnitSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\MetroSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\Okpd2SuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\Okved2SuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\PartySuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\PostalUnitSuggestProxyHandler;
use Module\Proxy\Proxies\DaData\Suggest\RegionCourtSuggestProxyHandler;
use Module\Proxy\Proxies\Test\TestEchoProxyHandler;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Module\Proxy\Proxies\Test\TestSuggestProxyHandler;

final class HandlerCatalog
{
    private function __construct() {}

    /** @return iterable<HandlerGroupDefinition> */
    public static function all(): iterable
    {
        yield HandlerGroupDefinition::make('AutoCRM')->handlers([
            HandlerDefinition::make(ModelsProxyHandler::class)->label('Список моделей'),
            HandlerDefinition::make(BrandsProxyHandler::class)->label('Список брендов'),
            HandlerDefinition::make(DealersProxyHandler::class)->label('Список дилеров'),
        ]);

        yield HandlerGroupDefinition::make('DaData / Подсказки')->handlers([
            HandlerDefinition::make(AddressSuggestProxyHandler::class)->label('Адрес'),
            HandlerDefinition::make(PartySuggestProxyHandler::class)->label('Организация'),
            HandlerDefinition::make(FioSuggestProxyHandler::class)->label('ФИО'),
            HandlerDefinition::make(BankSuggestProxyHandler::class)->label('Банк'),
            HandlerDefinition::make(PostalUnitSuggestProxyHandler::class)->label('Отделение почты'),
            HandlerDefinition::make(CountrySuggestProxyHandler::class)->label('Страна'),
            HandlerDefinition::make(EmailSuggestProxyHandler::class)->label('Email'),
            HandlerDefinition::make(FmsUnitSuggestProxyHandler::class)->label('Отделение ФМС'),
            HandlerDefinition::make(RegionCourtSuggestProxyHandler::class)->label('Участок мирового судьи'),
            HandlerDefinition::make(MetroSuggestProxyHandler::class)->label('Станция метро'),
            HandlerDefinition::make(CarBrandSuggestProxyHandler::class)->label('Марка автомобиля'),
            HandlerDefinition::make(CurrencySuggestProxyHandler::class)->label('Валюта'),
            HandlerDefinition::make(Okved2SuggestProxyHandler::class)->label('ОКВЭД2'),
            HandlerDefinition::make(Okpd2SuggestProxyHandler::class)->label('ОКПД2'),
        ]);

        yield HandlerGroupDefinition::make('DaData / Валидация (Clean)')->handlers([
            HandlerDefinition::make(AddressCleanProxyHandler::class)->label('Адрес'),
            HandlerDefinition::make(PhoneCleanProxyHandler::class)->label('Телефон'),
            HandlerDefinition::make(PassportCleanProxyHandler::class)->label('Паспорт'),
            HandlerDefinition::make(NameCleanProxyHandler::class)->label('ФИО'),
            HandlerDefinition::make(EmailCleanProxyHandler::class)->label('Email'),
        ]);

        yield HandlerGroupDefinition::make('Тест')->handlers([
            HandlerDefinition::make(TestLeadProxyHandler::class)->label('Лид'),
            HandlerDefinition::make(TestEchoProxyHandler::class)->label('Echo'),
            HandlerDefinition::make(TestSuggestProxyHandler::class)->label('Suggest'),
        ]);
    }

    /** @return list<array{class: string, label: string, group: string}> */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $group) {
            foreach ($group->getHandlers() as $handler) {
                $options[] = ['class' => $handler->class, 'label' => $handler->getLabel(), 'group' => $group->name];
            }
        }

        return $options;
    }

    /** @return list<string> */
    public static function classes(): array
    {
        return array_column(self::options(), 'class');
    }
}
