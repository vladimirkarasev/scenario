<?php

declare(strict_types=1);

namespace Module\Groups\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Groups\Models\UserGroup;
use Module\Users\Http\Resources\JsonApi\UserResource;

/**
 * @mixin UserGroup
 */
final class UserGroupResource extends JsonApiResource
{
    // true → ресурс уважает sparse fieldsets (?fields[groups]=name,slug),
    // чтобы во включённых группах отдавались только нужные поля.
    protected bool $usesRequestQueryString = true;

    public function toId(Request $request): string
    {
        return (string)$this->id;
    }

    public function toType(Request $request): string
    {
        return 'groups';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'ext_id' => $this->ext_id,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'members_count' => $this->members_count ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Связи отдаются как JSON:API include (?include=createdBy,updatedBy);
     * авторы (users) приходят в top-level `included`.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toRelationships(Request $request): array
    {
        return [
            'createdBy' => UserResource::class,
            'updatedBy' => UserResource::class,
        ];
    }
}
