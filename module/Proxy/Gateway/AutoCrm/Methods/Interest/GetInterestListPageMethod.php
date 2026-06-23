<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Interest;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetInterestListPageMethod extends AutoCrmListPageMethod
{
    public function key(): string
    {
        return 'autocrm.interest.list.page';
    }

    public function uri(): string
    {
        return '/api/lms/interest';
    }
}
