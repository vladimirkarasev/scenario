<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Result;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetResultMethod extends AutoCrmShowMethod
{
    public function key(): string
    {
        return 'autocrm.result.get';
    }

    public function uri(): string
    {
        return '/api/lms/result/'.$this->id;
    }
}
