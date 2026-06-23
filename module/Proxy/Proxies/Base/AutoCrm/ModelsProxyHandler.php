<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\AutoCrm;

use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\ProxyHandler;

class ModelsProxyHandler extends ProxyHandler
{
    public function fields(): iterable
    {
        yield ProxyFieldInteger::make('model_id')
            ->label('ID модели')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('model_name')
            ->label('Модель')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);

        yield ProxyFieldInteger::make('model_alias_id')
            ->label('ID алиаса модели')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldInteger::make('brand_id')
            ->label('ID бренда')
            ->nullable()
            ->rules(['nullable', 'integer']);
    }
}
