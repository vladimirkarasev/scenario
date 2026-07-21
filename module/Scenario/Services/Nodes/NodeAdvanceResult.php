<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

final readonly class NodeAdvanceResult
{
    public function __construct(
        public ?string $nextNodeId,
        public bool $runMutated = false,
        public bool $pause = false,
    ) {
    }

    public static function next(?string $nodeId): self
    {
        return new self($nodeId);
    }

    public static function mutated(): self
    {
        return new self(null, runMutated: true);
    }

    public static function pause(): self
    {
        return new self(null, pause: true);
    }
}
