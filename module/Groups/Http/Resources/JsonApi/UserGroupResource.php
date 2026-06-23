<?php

declare(strict_types=1);

namespace Module\Groups\Http\Resources\JsonApi;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Groups\Models\UserGroup;

/**
 * @mixin UserGroup
 */
final class UserGroupResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'groups';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'ext_id' => $this->ext_id,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'members_count' => $this->members_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function toRelationships(Request $request): array
    {
        return [
            'created_by' => fn () => $this->actorData($this->createdBy),
            'updated_by' => fn () => $this->actorData($this->updatedBy),
        ];
    }

    /** @return array{data: array{id: int, name: string|null, login: string|null}}|null */
    private function actorData(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login,
            ],
        ];
    }
}
