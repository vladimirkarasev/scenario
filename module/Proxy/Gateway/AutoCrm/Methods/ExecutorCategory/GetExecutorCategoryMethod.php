<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\ExecutorCategory;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetExecutorCategoryMethod extends AutoCrmShowMethod
{
    public function key(): string
    {
        return 'autocrm.executor-category.get';
    }

    public function uri(): string
    {
        return '/api/lms/executor-category/'.$this->id;
    }
}
