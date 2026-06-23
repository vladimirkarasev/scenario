<?php

declare(strict_types=1);

namespace Module\Groups\DTO;

use Illuminate\Http\Request;

final readonly class UserGroupData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $extId,
        public ?string $description,
        public bool $isActive,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            slug: $request->string('slug')->toString(),
            extId: $request->filled('ext_id') ? $request->string('ext_id')->toString() : null,
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            isActive: (bool) $request->boolean('is_active', true),
        );
    }
}
