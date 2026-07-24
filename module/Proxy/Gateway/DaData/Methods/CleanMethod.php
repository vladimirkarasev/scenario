<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class CleanMethod extends AbstractApiMethod
{
    public function __construct(
        private string $type,
        private string $value,
    ) {}

    #[\Override]
    public function key(): string
    {
        return 'dadata.clean.'.$this->type;
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return 'clean/'.$this->type;
    }

    /** @return list<string> */
    #[\Override]
    public function body(): array
    {
        return [$this->value];
    }
}
