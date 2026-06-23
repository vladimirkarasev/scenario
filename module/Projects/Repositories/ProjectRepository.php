<?php

declare(strict_types=1);

namespace Module\Projects\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Projects\Models\Project;

final class ProjectRepository
{
    /** @return LengthAwarePaginator<int, Project> */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Project::query()
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page[number]');
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Project
    {
        return Project::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Project $project, array $attributes): Project
    {
        $project->fill($attributes);
        $project->save();

        return $project;
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function activeBySitekeyAndHost(?string $sitekey, ?string $host): ?Project
    {
        return Project::query()
            ->where('sitekey', $sitekey)
            ->where('host', $host)
            ->where('is_active', true)
            ->first();
    }
}
