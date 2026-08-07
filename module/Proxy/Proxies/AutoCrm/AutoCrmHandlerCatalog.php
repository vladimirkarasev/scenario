<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\AutoCrm;

use Module\Proxy\DTO\HandlerDefinition;
use Module\Proxy\DTO\HandlerGroupDefinition;

final class AutoCrmHandlerCatalog
{
    private function __construct()
    {
    }

    /** @return iterable<HandlerGroupDefinition> */
    public static function handlers(): iterable
    {
        yield HandlerGroupDefinition::make('AutoCRM')->handlers([
            HandlerDefinition::make(ModelsProxyHandler::class)->label('Список моделей'),
            HandlerDefinition::make(BrandsProxyHandler::class)->label('Список брендов'),
            HandlerDefinition::make(DealersProxyHandler::class)->label('Список дилеров'),
        ]);
    }
}