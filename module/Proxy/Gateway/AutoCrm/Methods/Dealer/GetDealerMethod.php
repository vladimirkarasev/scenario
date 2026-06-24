<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Dealer;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetDealerMethod extends AutoCrmShowMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.dealer.get';
    }

    public function uri(): string
    {
        return '/api/dealer/'.$this->id;
    }
}
