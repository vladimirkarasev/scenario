<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioActionData
{
    public function __construct(
        public ?int $actorId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            actorId: $request->user()?->id,
        );
    }
}
