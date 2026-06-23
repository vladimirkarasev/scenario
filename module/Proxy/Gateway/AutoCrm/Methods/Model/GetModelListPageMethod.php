<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Model;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetModelListPageMethod extends AutoCrmListPageMethod
{
    public function key(): string
    {
        return 'autocrm.model.list.page';
    }

    public function uri(): string
    {
        return '/api/model';
    }
}
