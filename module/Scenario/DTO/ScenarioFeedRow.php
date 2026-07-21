<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use App\Models\Category;
use Module\Scenario\Models\Scenario;
use Module\Users\Models\User;

final readonly class ScenarioFeedRow
{
    /**
     * @param  list<string>|null  $pathIds
     * @param  list<string>|null  $tags
     * @param  array{id: int, name: string|null, fio: string|null, login: string|null}|null  $createdBy
     * @param  array{id: int, name: string|null, fio: string|null, login: string|null}|null  $updatedBy
     */
    private function __construct(
        public string $itemType,
        public string $id,
        public string $name,
        public ?string $parentId = null,
        public ?string $parentPath = null,
        public ?array $pathIds = null,
        public ?int $childrenCount = null,
        public ?string $alias = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?string $folderId = null,
        public ?string $folderPath = null,
        public ?array $tags = null,
        public ?int $versionsCount = null,
        public ?string $activeVersionId = null,
        public ?array $createdBy = null,
        public ?array $updatedBy = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }

    /** @param  list<string>|null  $pathIds */
    public static function category(Category $category, ?string $parentPath, ?array $pathIds): self
    {
        return new self(
            itemType: 'category',
            id: $category->id,
            name: $category->name,
            parentId: $category->parent_id,
            parentPath: $parentPath,
            pathIds: $pathIds,
            childrenCount: $category->children_count ?? 0,
            createdAt: $category->created_at?->toIso8601String(),
            updatedAt: $category->updated_at?->toIso8601String(),
        );
    }

    public static function scenario(Scenario $scenario, ?string $folderId, ?string $folderPath): self
    {
        return new self(
            itemType: 'scenario',
            id: $scenario->id,
            name: $scenario->name,
            alias: $scenario->alias,
            description: $scenario->description,
            status: $scenario->status->value,
            folderId: $folderId,
            folderPath: $folderPath,
            tags: array_values($scenario->tags ?? []),
            versionsCount: $scenario->versions_count ?? 0,
            activeVersionId: $scenario->active_version_id,
            createdBy: self::actor($scenario->createdBy),
            updatedBy: self::actor($scenario->updatedBy),
            createdAt: $scenario->created_at?->toIso8601String(),
            updatedAt: $scenario->updated_at?->toIso8601String(),
        );
    }

    /** @return array{id: int, name: string|null, fio: string|null, login: string|null}|null */
    private static function actor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'fio' => $user->fio,
            'login' => $user->login,
        ];
    }
}
