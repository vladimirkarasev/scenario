<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioRunJumpData
{
    public function __construct(
        public string $nodeId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            nodeId: $request->string('node_id')->toString(),
        );
    }
}
