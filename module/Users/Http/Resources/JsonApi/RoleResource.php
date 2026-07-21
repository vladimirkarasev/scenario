<?php

declare(strict_types=1);

namespace Module\Users\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Users\Models\Role;

/**
 * @mixin Role
 */
final class RoleResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = true;

    public function toId(Request $request): string
    {
        return (string)$this->id;
    }

    public function toType(Request $request): string
    {
        return 'roles';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'guard_name' => $this->guard_name,
            'permissions' => $this->permissions->pluck('name')->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    #[\Override]
    protected function resolveResourceRelationshipIdentifiers(JsonApiRequest $request): array
    {
        return [
            'users' => ['meta' => ['count' => $this->users_count ?? 0]],
        ];
    }
}
