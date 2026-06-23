<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Contracts;

interface ApiMethod
{
    public function key(): string;

    public function method(): string;

    public function uri(): string;

    /** @return array<string, mixed> */
    public function query(): array;

    /** @return array<string, mixed> */
    public function headers(): array;

    public function body(): mixed;

    /** @return array<string, mixed> */
    public function options(): array;
}
