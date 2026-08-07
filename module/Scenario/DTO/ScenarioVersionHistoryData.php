<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioVersionHistoryData
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            page: max(1, $request->integer('page.number', 1)),
            perPage: min(100, max(1, $request->integer('page.size', 20))),
        );
    }
}
