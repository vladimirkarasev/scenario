<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\Proxies\Base\AutoCrm\BrandsProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\DealersProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler;
use Module\Proxy\Proxies\Test\TestEchoProxyHandler;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Module\Proxy\Proxies\Test\TestSuggestProxyHandler;

/**
 * Каталог обработчиков (endpoint_handler), которые разработчик пишет в коде. Админ
 * выбирает из него обработчик при создании интеграции в UI. Новый обработчик =
 * добавить класс сюда (namespace-проверка остаётся в HandlerResolver).
 */
final class HandlerCatalog
{
    private function __construct() {}

    /** @return array<class-string, string> */
    public static function all(): array
    {
        return [
            ModelsProxyHandler::class => 'AutoCRM: список моделей',
            BrandsProxyHandler::class => 'AutoCRM: список брендов',
            DealersProxyHandler::class => 'AutoCRM: список дилеров',
            TestLeadProxyHandler::class => 'Тест: лид',
            TestEchoProxyHandler::class => 'Тест: echo',
            TestSuggestProxyHandler::class => 'Тест: suggest',
        ];
    }

    public static function has(string $class): bool
    {
        return array_key_exists($class, self::all());
    }

    /** @return array<int, array{class: string, label: string}> */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $class => $label) {
            $options[] = ['class' => $class, 'label' => $label];
        }

        return $options;
    }
}
