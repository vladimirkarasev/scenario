<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Brand;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetBrandMethod extends AutoCrmShowMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.brand.get';
    }

    public function uri(): string
    {
        return '/api/brand/'.$this->id;
    }
}
