<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class SuggestMethod extends AbstractApiMethod
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private string $type,
        private string $query,
        private int $count = 5,
        private array $options = [],
    ) {}

    #[\Override]
    public function key(): string
    {
        return 'dadata.suggest.'.$this->type;
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return 'suggest/'.$this->type;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function body(): array
    {
        return ['query' => $this->query, 'count' => $this->count, ...$this->options];
    }
}
