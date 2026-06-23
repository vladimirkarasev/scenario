<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioData
{
    /**
     * @param list<string> $tags
     * @param list<string> $categoryIds
     * @param list<string> $groupIds
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
        public ?string $alias,
        public array $tags,
        public array $categoryIds,
        public array $groupIds,
        public ?int $actorId,
        public ?string $activeVersionId = null,
        public bool $hasActiveVersion = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            isActive: $request->boolean('is_active'),
            alias: $request->filled('alias') ? $request->string('alias')->toString() : null,
            tags: self::stringArray($request->input('tags')),
            categoryIds: self::stringArray($request->input('category_ids')),
            groupIds: self::stringArray($request->input('group_ids')),
            actorId: $request->user()?->id,
            activeVersionId: $request->filled('active_version_id') ? $request->string('active_version_id')->toString() : null,
            hasActiveVersion: $request->has('active_version_id'),
        );
    }

    /** @return list<string> */
    private static function stringArray(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $raw),
            static fn (string $v): bool => $v !== '',
        )));
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        $attributes = [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'alias' => $this->alias,
            'tags' => $this->tags,
        ];

        if ($this->hasActiveVersion) {
            $attributes['active_version_id'] = $this->activeVersionId;
        }

        return $attributes;
    }
}
