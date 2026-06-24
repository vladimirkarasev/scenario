<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\RequestType;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetRequestTypeMethod extends AutoCrmShowMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.request-type.get';
    }

    public function uri(): string
    {
        return '/api/lms/request-type/'.$this->id;
    }
}
