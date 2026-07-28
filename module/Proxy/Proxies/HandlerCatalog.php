<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies;

use Module\Proxy\DTO\HandlerGroupDefinition;
use Module\Proxy\Proxies\AutoCrm\AutoCrmHandlerCatalog;
use Module\Proxy\Proxies\DaData\DaDataHandlerCatalog;

final class HandlerCatalog
{
    private function __construct()
    {
    }

    /** @return iterable<HandlerGroupDefinition> */
    public static function all(): iterable
    {
        yield from AutoCrmHandlerCatalog::handlers();

        yield from DaDataHandlerCatalog::handlers();
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
