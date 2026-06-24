<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods;

use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmListQuery;
use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

abstract readonly class AutoCrmListPageMethod extends AbstractApiMethod
{
    public function __construct(
        protected AutoCrmListQuery $query,
        protected int $page,
    ) {}

    final public function method(): string
    {
        return 'GET';
    }

    /** @return array<string, mixed> */
    #[\Override]
    final public function query(): array
    {
        return $this->query->forPage($this->page);
    }
}
