<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods;

use Module\Proxy\Gateway\Base\Methods\AbstractApiMethod;

final readonly class CreateLeadMethod extends AbstractApiMethod
{
    /**
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $query
     */
    public function __construct(
        private array $lead,
        private array $query = [],
    ) {}

    #[\Override]
    public function key(): string
    {
        return 'autocrm.leads.create';
    }

    public function method(): string
    {
        return 'POST';
    }

    public function uri(): string
    {
        return '/api/leads';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->lead;
    }
}
