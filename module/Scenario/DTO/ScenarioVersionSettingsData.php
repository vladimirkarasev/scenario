<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioVersionSettingsData
{
    public function __construct(
        public ?string $name,
        public string $status,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->filled('name') ? trim($request->string('name')->toString()) : null,
            status: $request->string('status', 'draft')->toString(),
        );
    }

    /** @return array{name: string|null, status: string} */
    public function toAttributes(): array
    {
        return ['name' => $this->name, 'status' => $this->status];
    }
}
