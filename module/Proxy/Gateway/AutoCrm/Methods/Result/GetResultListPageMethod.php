<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Result;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetResultListPageMethod extends AutoCrmListPageMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.result.list.page';
    }

    public function uri(): string
    {
        return '/api/lms/result';
    }
}
