<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\ExecutorCategory;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;

final readonly class GetExecutorCategoryListPageMethod extends AutoCrmListPageMethod
{
    #[\Override]
    public function key(): string
    {
        return 'autocrm.executor-category.list.page';
    }

    public function uri(): string
    {
        return '/api/lms/executor-category';
    }
}
