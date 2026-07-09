<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Scenario\DTO\ScenarioFeedRow;

/** @property ScenarioFeedRow $resource */
final class ScenarioFeedResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return $this->resource->id;
    }

    public function toType(Request $request): string
    {
        return $this->resource->itemType;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return match ($this->resource->itemType) {
            'category' => [
                'name' => $this->resource->name,
                'parent_id' => $this->resource->parentId,
                'parent_path' => $this->resource->parentPath,
                'path_ids' => $this->resource->pathIds,
                'children_count' => $this->resource->childrenCount,
                'created_at' => $this->resource->createdAt,
                'updated_at' => $this->resource->updatedAt,
            ],
            default => [
                'name' => $this->resource->name,
                'alias' => $this->resource->alias,
                'description' => $this->resource->description,
                'status' => $this->resource->status,
                'folder_id' => $this->resource->folderId,
                'folder_path' => $this->resource->folderPath,
                'tags' => $this->resource->tags,
                'versions_count' => $this->resource->versionsCount,
                'active_version_id' => $this->resource->activeVersionId,
                'created_by' => $this->resource->createdBy,
                'updated_by' => $this->resource->updatedBy,
                'created_at' => $this->resource->createdAt,
                'updated_at' => $this->resource->updatedAt,
            ],
        };
    }
}
