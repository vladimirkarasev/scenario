<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioVersionActionData
{
    public function __construct(
        public ?int $actorId = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            actorId: $request->user()?->id,
        );
    }
}
