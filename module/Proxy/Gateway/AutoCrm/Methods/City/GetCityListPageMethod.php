<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\City;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetCityListPageMethod extends AutoCrmListPageMethod
{
    public function key(): string
    {
        return 'autocrm.city.list.page';
    }

    public function uri(): string
    {
        return '/api/city';
    }
}
