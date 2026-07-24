<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class IplocateMethod extends AbstractApiMethod
{
    public function __construct(private string $ip) {}

    #[\Override]
    public function key(): string
    {
        return 'dadata.iplocate';
    }

    public function method(): string
    {
        return 'GET';
    }

    public function uri(): string
    {
        return 'iplocate/address';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function query(): array
    {
        return ['ip' => $this->ip];
    }
}
