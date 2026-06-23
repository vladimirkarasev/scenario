<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Distributor;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetDistributorListPageMethod extends AutoCrmListPageMethod
{
    public function key(): string
    {
        return 'autocrm.distributor.list.page';
    }

    public function uri(): string
    {
        return '/api/distributor';
    }
}
