<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class FindByIdMethod extends AbstractApiMethod
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private string $type,
        private string $query,
        private int $count = 1,
        private array $options = [],
    ) {}

    #[\Override]
    public function key(): string
    {
        return 'dadata.find_by_id.'.$this->type;
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return 'findById/'.$this->type;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function body(): array
    {
        return ['query' => $this->query, 'count' => $this->count, ...$this->options];
    }
}
