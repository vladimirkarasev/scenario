<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Fields;

final readonly class RenderedBlockField
{
    /** @param  array<string, mixed>  $props */
    public function __construct(
        public string $id,
        public string $type,
        public array $props,
    ) {
    }

    /** @return array{id: string, type: string, props: array<string, mixed>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'props' => $this->props];
    }
}
