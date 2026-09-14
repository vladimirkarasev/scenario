<?php

declare(strict_types=1);

namespace Module\Directories\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;

final class DirectoryImportRepository
{
    /** @return LengthAwarePaginator<int, DirectoryImport> */
    public function paginateForDirectory(Directory $directory, ?int $versionId, int $perPage): LengthAwarePaginator
    {
        return DirectoryImport::query()
            ->where('directory_id', $directory->id)
            ->when(
                $versionId !== null,
                static fn (Builder $query): Builder => $query->where('directory_version_id', $versionId),
            )
            ->latest()
            ->paginate($perPage);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): DirectoryImport
    {
        return DirectoryImport::query()->create($attributes);
    }

    public function lockForUpdateOrFail(int $id): DirectoryImport
    {
        return DirectoryImport::query()
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function findOrFail(int $id): DirectoryImport
    {
        return DirectoryImport::query()->findOrFail($id);
    }

    public function findWithVersionOrFail(int $id): DirectoryImport
    {
        return DirectoryImport::query()
            ->with('version')
            ->findOrFail($id);
    }

    public function find(int $id): ?DirectoryImport
    {
        return DirectoryImport::query()->find($id);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(DirectoryImport $import, array $attributes): DirectoryImport
    {
        $import->forceFill($attributes)->save();

        return $import;
    }
}
