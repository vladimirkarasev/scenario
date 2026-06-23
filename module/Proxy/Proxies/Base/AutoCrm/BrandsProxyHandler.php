<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Base\AutoCrm;

use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\ProxyHandler;

class BrandsProxyHandler extends ProxyHandler
{
    public function fields(): iterable
    {
        yield ProxyFieldInteger::make('brand_id')
            ->label('ID бренда')
            ->nullable()
            ->rules(['nullable', 'integer']);

        yield ProxyFieldString::make('brand_name')
            ->label('Бренд')
            ->nullable()
            ->rules(['nullable', 'string', 'max:255']);
    }
}
