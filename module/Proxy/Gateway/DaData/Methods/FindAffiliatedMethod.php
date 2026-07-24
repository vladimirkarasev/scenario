<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class FindAffiliatedMethod extends AbstractApiMethod
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private string $inn,
        private int $count = 5,
        private array $options = [],
    ) {}

    #[\Override]
    public function key(): string
    {
        return 'dadata.find_affiliated';
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return 'findAffiliated/party';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function body(): array
    {
        return ['query' => $this->inn, 'count' => $this->count, ...$this->options];
    }
}
