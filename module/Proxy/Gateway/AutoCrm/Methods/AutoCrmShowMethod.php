<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

abstract readonly class AutoCrmShowMethod extends AbstractApiMethod
{
    public function __construct(protected int $id) {}

    final public function method(): string
    {
        return 'GET';
    }
}
