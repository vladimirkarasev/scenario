<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\Proxies\Base\AutoCrm\BrandsProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\DealersProxyHandler;
use Module\Proxy\Proxies\Base\AutoCrm\ModelsProxyHandler;
use Module\Proxy\Proxies\Test\TestEchoProxyHandler;
use Module\Proxy\Proxies\Test\TestLeadProxyHandler;
use Module\Proxy\Proxies\Test\TestSuggestProxyHandler;

final class HandlerCatalog
{
    private function __construct() {}

    /** @return array<class-string, array{label: string, group: string}> */
    public static function all(): array
    {
        return [
            ModelsProxyHandler::class => ['label' => 'Список моделей', 'group' => 'AutoCRM'],
            BrandsProxyHandler::class => ['label' => 'Список брендов', 'group' => 'AutoCRM'],
            DealersProxyHandler::class => ['label' => 'Список дилеров', 'group' => 'AutoCRM'],
            TestLeadProxyHandler::class => ['label' => 'Лид', 'group' => 'Тест'],
            TestEchoProxyHandler::class => ['label' => 'Echo', 'group' => 'Тест'],
            TestSuggestProxyHandler::class => ['label' => 'Suggest', 'group' => 'Тест'],
        ];
    }

    public static function has(string $class): bool
    {
        return array_key_exists($class, self::all());
    }

    /** @return array<int, array{class: string, label: string, group: string}> */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $class => $meta) {
            $options[] = ['class' => $class, 'label' => $meta['label'], 'group' => $meta['group']];
        }

        return $options;
    }

    /**
     * @return array{items: array<int, array{class: string, label: string, group: string}>, total: int}
     */
    public static function search(?string $query, int $page, int $perPage): array
    {
        $all = self::options();

        if ($query !== null && $query !== '') {
            $needle = mb_strtolower($query);
            $all = array_values(array_filter($all, static function (array $o) use ($needle): bool {
                $haystack = mb_strtolower($o['label'].' '.$o['group'].' '.$o['class']);

                return str_contains($haystack, $needle);
            }));
        }

        $total = count($all);

        return ['items' => array_slice($all, ($page - 1) * $perPage, $perPage), 'total' => $total];
    }
}
