<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Categories\Http\Resources\JsonApi\CategoryResource;
use Module\Scenario\Models\Scenario;

/**
 * @mixin Scenario
 */
final class ScenariosResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;
    protected bool $includesPreviouslyLoadedRelationships = true;

    public function toType(Request $request): string
    {
        return 'scenario';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'status' => $this->status->value,
            'alias' => $this->alias,
            'tags' => $this->tags ?? [],
            'active_version_id' => $this->active_version_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn() => $this->createdBy !== null ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'login' => $this->createdBy->login,
                'fio' => $this->createdBy->fio,
            ] : null),
            'updated_by' => $this->whenLoaded('updatedBy', fn() => $this->updatedBy !== null ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
                'login' => $this->updatedBy->login,
                'fio' => $this->updatedBy->fio,
            ] : null),
            'versions' => $this->whenLoaded('versions', fn() => $this->versions->map(fn($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'status' => $v->status,
                'schema_version' => null,
                'created_at' => $v->created_at?->toIso8601String(),
                'updated_at' => $v->updated_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toRelationships(Request $request): array
    {
        return [
            'categories' => CategoryResource::class,
        ];
    }

    #[\Override]
    protected static function newCollection($resource): ScenariosResourceCollection
    {
        return new ScenariosResourceCollection($resource, self::class);
    }
}
