<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\City;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetCityMethod extends AutoCrmShowMethod
{
    public function key(): string
    {
        return 'autocrm.city.get';
    }

    public function uri(): string
    {
        return '/api/city/'.$this->id;
    }
}
