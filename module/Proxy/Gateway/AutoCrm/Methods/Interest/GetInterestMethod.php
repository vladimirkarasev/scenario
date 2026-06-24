<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Interest;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetInterestMethod extends AutoCrmShowMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.interest.get';
    }

    public function uri(): string
    {
        return '/api/lms/interest/'.$this->id;
    }
}
