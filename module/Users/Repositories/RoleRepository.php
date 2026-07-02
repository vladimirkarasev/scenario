<?php

declare(strict_types=1);

namespace Module\Users\Repositories;

use App\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Users\Enums\UserErrorCode;
use Module\Users\Models\Role;
use Module\Users\QueryBuilders\RoleBuilder;

final class RoleRepository
{
    /** @return LengthAwarePaginator<int, Role> */
    public function paginate(int $perPage, ?string $search): LengthAwarePaginator
    {
        return $this->query()
            ->withCount('users')
            ->with('permissions')
            ->search($search)
            ->ordered()
            ->paginate($perPage, ['*'], 'page[number]');
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Role>
     */
    public function findByNames(array $names): Collection
    {
        return $this->query()
            ->forGuard()
            ->withNames($names)
            ->with('permissions')
            ->get();
    }

    public function findByName(string $name): ?Role
    {
        return $this->query()
            ->forGuard()
            ->withName($name)
            ->first();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Role
    {
        return Role::query()->create($attributes);
    }

    public function findForUpdate(Role $role): Role
    {
        return $this->query()->whereKey($role->getKey())->lockForUpdate()->first()
            ?? throw NotFoundException::make('Роль не найдена.', UserErrorCode::RoleNotFound, 'Роль не найдена');
    }

    /** @param array<string, mixed> $attributes */
    public function firstOrCreateByName(string $name, array $attributes): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            $attributes,
        );
    }

    private function query(): RoleBuilder
    {
        /** @var RoleBuilder $query */
        $query = Role::query();

        return $query;
    }
}
