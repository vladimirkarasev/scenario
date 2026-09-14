<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

interface BlockFieldInterface
{
    /** @return array{id: string, type: string, props: array<string, mixed>} */
    public function toArray(): array;
}
