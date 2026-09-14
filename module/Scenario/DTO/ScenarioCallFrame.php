<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

final readonly class ScenarioCallFrame
{
    public function __construct(
        public ?string $versionId,
        public ?string $revisionId,
        public ?string $returnNodeId,
    ) {
    }

    /** @param  array<array-key, mixed>  $frame */
    public static function fromArray(array $frame): self
    {
        return new self(
            versionId: is_string($frame['version_id'] ?? null) ? $frame['version_id'] : null,
            revisionId: is_string($frame['revision_id'] ?? null) ? $frame['revision_id'] : null,
            returnNodeId: is_string($frame['return_node_id'] ?? null) ? $frame['return_node_id'] : null,
        );
    }

    /** @return array{version_id: string|null, revision_id: string|null, return_node_id: string|null} */
    public function toArray(): array
    {
        return [
            'version_id' => $this->versionId,
            'revision_id' => $this->revisionId,
            'return_node_id' => $this->returnNodeId,
        ];
    }
}
