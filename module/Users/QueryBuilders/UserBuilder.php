<?php

declare(strict_types=1);

namespace Module\Users\QueryBuilders;

use Module\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<User>
 */
final class UserBuilder extends Builder
{
    public function forProject(string $projectId): static
    {
        return $this->where('users.project_id', $projectId);
    }

    public function notSystem(): static
    {
        return $this->where('users.is_system', false);
    }

    public function system(): static
    {
        return $this->where('users.is_system', true);
    }

    public function withExternalId(string $externalId): static
    {
        return $this->where('users.external_id', $externalId);
    }

    public function withRoleName(string $roleName): static
    {
        return $this->whereHas(
            'roles',
            static fn (Builder $query) => $query->where('roles.name', $roleName),
        );
    }

    public function ordered(): static
    {
        return $this->orderBy('users.name')->orderBy('users.id');
    }

    /** @param  list<string>  $groupIds */
    public function forGroups(array $groupIds): static
    {
        if ($groupIds === []) {
            return $this;
        }

        return $this->whereHas(
            'groups',
            static fn(Builder $q) => $q->whereIn('user_groups.id', $groupIds),
        );
    }

    /** @param  list<string>  $roleIds */
    public function forRoles(array $roleIds): static
    {
        if ($roleIds === []) {
            return $this;
        }

        return $this->whereHas(
            'roles',
            static fn(Builder $q) => $q->whereIn('roles.id', $roleIds),
        );
    }

    public function search(?string $value): static
    {
        if ($value === null || $value === '') {
            return $this;
        }

        $like = '%'.mb_strtolower($value).'%';

        return $this->where(static function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(name) like ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(fio, \'\')) like ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) like ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(login, \'\')) like ?', [$like]);
        });
    }
}
