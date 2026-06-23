<?php

declare(strict_types=1);

namespace Module\Categories\Http\Resources\JsonApi;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin Category
 */
final class CategoryResource extends JsonApiResource
{
    // true → ресурс уважает sparse fieldsets из query (?fields[category]=name,parent_id).
    // Без параметра fields поведение прежнее: возвращаются все атрибуты.
    protected bool $usesRequestQueryString = true;

    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'category';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'group_ids' => $this->relationLoaded('groups')
                ? $this->groups->pluck('id')->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveResourceRelationshipIdentifiers(JsonApiRequest $request): array
    {
        /** @var array<string, mixed> $base */
        $base = parent::resolveResourceRelationshipIdentifiers($request);

        return array_merge($base, [
            'children' => ['meta' => ['count' => $this->children_count ?? 0]],
        ]);
    }
}
