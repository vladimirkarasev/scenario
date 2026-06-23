<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Methods;

use Module\Proxy\Gateway\Base\Contracts\ApiMethod;

abstract readonly class AbstractApiMethod implements ApiMethod
{
    public function key(): string
    {
        return static::class;
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function headers(): array
    {
        return [];
    }

    public function body(): mixed
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return [];
    }
}
