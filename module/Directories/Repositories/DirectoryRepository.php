<?php

declare(strict_types=1);

namespace Module\Directories\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Projects\Models\Project;

final class DirectoryRepository
{
    /** @return EloquentCollection<int, Directory> */
    public function listForProject(Project $project): EloquentCollection
    {
        return $this->listQuery($project)->get();
    }

    /**
     * @param  string[]  $categoryIds
     * @return LengthAwarePaginator<int, Directory>
     */
    public function paginateForProject(
        Project $project,
        int $page,
        int $perPage,
        array $categoryIds = [],
        bool $uncategorized = false
    ): LengthAwarePaginator {
        $q = $this->listQuery($project);

        if ($categoryIds !== []) {
            $q->whereHas('categories', static fn(Builder $sub) => $sub->whereIn('categories.id', $categoryIds));
        } elseif ($uncategorized) {
            $q->whereDoesntHave('categories');
        }

        return $q->paginate(perPage: $perPage, page: $page);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Directory
    {
        return Directory::query()->create($attributes);
    }

    public function findActiveBySlugOrFail(string $slug): Directory
    {
        return Directory::query()
            ->where('slug', $slug)
            ->whereHas('activeVersion')
            ->firstOrFail();
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Directory $directory, array $attributes): Directory
    {
        $directory->fill($attributes);
        $directory->save();

        return $directory;
    }

    public function delete(Directory $directory): void
    {
        $directory->delete();
    }

    public function loadDetail(Directory $directory): Directory
    {
        return $directory->load([
            'categories',
            'activeVersion',
            'versions' => fn(Relation $q) => $q->withCount(['items', 'imports'])->orderByDesc('version_number'),
            'latestVersion',
            'imports' => fn(Relation $q) => $q->latest()->limit(20),
        ]);
    }

    public function freshForPayload(Directory $directory): Directory
    {
        return $directory->fresh(['categories', 'activeVersion', 'latestVersion', 'imports']) ?? $directory;
    }

    public function resolveEditableVersion(Directory $directory): DirectoryVersion
    {
        return $directory->versions()->where('is_active', true)->first()
            ?? $directory->versions()->first()
            ?? $directory->versions()->create([
                'version_number' => 1,
                'status' => 'draft',
                'is_active' => true,
                'schema_json' => [],
            ]);
    }

    /** @param  array<int, array<string, mixed>>  $fields */
    public function createInitialVersion(
        Directory $directory,
        array $fields,
        string $sourceType = 'manual'
    ): DirectoryVersion {
        return $directory->versions()->create([
            'version_number' => 1,
            'status' => 'draft',
            'is_active' => true,
            'source_type' => $sourceType,
            'schema_json' => $fields,
        ]);
    }

    public function activeVersion(Directory $directory): ?DirectoryVersion
    {
        return $directory->versions()
            ->where('is_active', true)
            ->first();
    }

    public function firstVersion(Directory $directory): ?DirectoryVersion
    {
        return $directory->versions()->first();
    }

    public function findVersion(Directory $directory, string $versionId): ?DirectoryVersion
    {
        return $directory->versions()->find($versionId);
    }

    public function versionsCount(Directory $directory): int
    {
        return $directory->versions()->count();
    }

    /** @return Builder<Directory> */
    private function listQuery(Project $project): Builder
    {
        return Directory::query()
            ->where('project_id', $project->id)
            ->with([
                'categories',
                'activeVersion',
                'latestVersion',
                'imports' => fn(Relation $q) => $q->latest()->limit(5),
            ])
            ->withCount('versions')
            ->orderBy('name');
    }
}
