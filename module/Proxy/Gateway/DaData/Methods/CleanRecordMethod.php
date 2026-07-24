<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class CleanRecordMethod extends AbstractApiMethod
{
    /**
     * @param list<string>         $structure
     * @param array<string, mixed> $record
     */
    public function __construct(
        private array $structure,
        private array $record,
    ) {}

    #[\Override]
    public function key(): string
    {
        return 'dadata.clean_record';
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return 'clean';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function body(): array
    {
        return ['structure' => $this->structure, 'data' => [$this->record]];
    }
}
