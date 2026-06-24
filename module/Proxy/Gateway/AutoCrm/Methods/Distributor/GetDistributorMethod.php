<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Distributor;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetDistributorMethod extends AutoCrmShowMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.distributor.get';
    }

    public function uri(): string
    {
        return '/api/distributor/'.$this->id;
    }
}
