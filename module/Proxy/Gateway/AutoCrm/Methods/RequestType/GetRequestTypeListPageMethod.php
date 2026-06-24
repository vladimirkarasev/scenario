<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\RequestType;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetRequestTypeListPageMethod extends AutoCrmListPageMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.request-type.list.page';
    }

    public function uri(): string
    {
        return '/api/lms/request-type';
    }
}
