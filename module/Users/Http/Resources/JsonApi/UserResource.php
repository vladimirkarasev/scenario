<?php

declare(strict_types=1);

namespace Module\Users\Http\Resources\JsonApi;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Module\Groups\Models\UserGroup;

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
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->name,
            'fio' => $this->fio,
            'email' => $this->email,
            'login' => $this->login,
            'external_id' => $this->external_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    protected function resolveResourceRelationshipIdentifiers(JsonApiRequest $request): array
    {
        $result = [];

        if ($this->relationLoaded('roles')) {
            $result['roles'] = [
                'data' => $this->roles->map(static fn (Role $r): array => [
                    'type' => 'roles',
                    'id' => (string) $r->id,
                    'meta' => ['name' => $r->name, 'title' => $r->title],
                ])->all(),
            ];
        }

        if ($this->relationLoaded('groups')) {
            $result['groups'] = [
                'data' => $this->groups->map(static fn (UserGroup $g): array => [
                    'type' => 'groups',
                    'id' => $g->id,
                    'meta' => ['name' => $g->name, 'slug' => $g->slug],
                ])->all(),
            ];
        }

        return $result;
    }
}
