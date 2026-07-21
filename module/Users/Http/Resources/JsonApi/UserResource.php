<?php

declare(strict_types=1);

namespace Module\Users\Http\Resources\JsonApi;

use Module\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Groups\Http\Resources\JsonApi\UserGroupResource;

/**
 * @mixin User
 */
final class UserResource extends JsonApiResource
{
    protected bool $usesRequestQueryString = false;

    public function toId(Request $request): string
    {
        return (string) $this->id;
    }

    public function toType(Request $request): string
    {
        return 'users';
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'fio' => $this->fio,
            'email' => $this->email,
            'login' => $this->login,
            'external_id' => $this->external_id,
            'is_system' => $this->is_system,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toRelationships(Request $request): array
    {
        return [
            'roles' => RoleResource::class,
            'groups' => UserGroupResource::class,
        ];
    }
}
