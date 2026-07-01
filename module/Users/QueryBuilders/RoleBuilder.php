<?php

declare(strict_types=1);

namespace Module\Users\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Module\Users\Models\Role;

/** @extends Builder<Role> */
final class RoleBuilder extends Builder
{
    public function forGuard(string $guard = 'web'): static
    {
        return $this->where('roles.guard_name', $guard);
    }

    public function withName(string $name): static
    {
        return $this->where('roles.name', $name);
    }

    /** @param list<string> $names */
    public function withNames(array $names): static
    {
        return $this->whereIn('roles.name', $names);
    }

    public function system(): static
    {
        return $this->where('roles.is_system', true);
    }

    public function search(?string $value): static
    {
        if ($value === null || $value === '') {
            return $this;
        }

        $like = '%'.mb_strtolower($value).'%';

        return $this->where(static fn (Builder $query) => $query
            ->whereRaw('LOWER(roles.name) like ?', [$like])
            ->orWhereRaw('LOWER(COALESCE(roles.title, \'\')) like ?', [$like]));
    }

    public function ordered(): static
    {
        return $this->orderBy('roles.name')->orderBy('roles.id');
    }
}
