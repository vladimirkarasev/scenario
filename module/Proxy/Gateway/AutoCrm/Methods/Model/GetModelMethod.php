<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm\Methods\Model;

use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmShowMethod;

final readonly class GetModelMethod extends AutoCrmShowMethod
{
    public function __construct(int $id, private ?string $expand = null)
    {
        parent::__construct($id);
    }

    #[\Override]
    public function key(): string
    {
        return 'autocrm.model.get';
    }

    public function uri(): string
    {
        return '/api/model/'.$this->id;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function query(): array
    {
        return array_filter([
            'expand' => $this->expand,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
