<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class SurveyIndexData
{
    public function __construct(
        public ?string $status = null,
        public ?string $scenarioId = null,
        public int $perPage = 20,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            status: $request->filled('status') ? $request->string('status')->toString() : null,
            scenarioId: $request->filled('scenario_id') ? $request->string('scenario_id')->toString() : null,
            perPage: max(1, min(100, (int)$request->integer('per_page', 20))),
        );
    }
}
