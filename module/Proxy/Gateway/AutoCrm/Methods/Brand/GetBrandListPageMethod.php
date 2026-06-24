<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Brand;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetBrandListPageMethod extends AutoCrmListPageMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.brand.list.page';
    }

    public function uri(): string
    {
        return '/api/brand';
    }
}
