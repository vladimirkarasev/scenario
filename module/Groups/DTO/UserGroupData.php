<?php

declare(strict_types=1);

namespace Module\Groups\DTO;

use Illuminate\Http\Request;
use Module\Groups\Models\UserGroup;

final readonly class UserGroupData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $extId,
        public ?string $description,
        public bool $isActive,
        public bool $extIdProvided = true,
        public bool $descriptionProvided = true,
        public bool $isActiveProvided = true,
    ) {
    }

    public static function fromRequest(Request $request, ?UserGroup $current = null): self
    {
        $currentName = $current instanceof UserGroup ? $current->name : '';
        $currentSlug = $current instanceof UserGroup ? $current->slug : '';

        return new self(
            name: $request->exists('name')
                ? $request->string('name')->toString()
                : $currentName,
            slug: $request->exists('slug')
                ? $request->string('slug')->toString()
                : $currentSlug,
            extId: $request->filled('ext_id') ? $request->string('ext_id')->toString() : null,
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            isActive: (bool)$request->boolean('is_active', true),
            extIdProvided: $request->exists('ext_id'),
            descriptionProvided: $request->exists('description'),
            isActiveProvided: $request->exists('is_active'),
        );
    }
}
