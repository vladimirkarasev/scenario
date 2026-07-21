<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Module\Directories\DTO\DirectoryFeedData;
use Module\Directories\Models\Directory;

final readonly class DirectoryFeedService
{
    /**
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, folders_total: int, items_total: int}
     * }
     */
    public function feed(?string $projectId, DirectoryFeedData $data): array
    {
        $foldersTotal = $this->foldersQuery($projectId, $data)->count();
        $itemsTotal = $this->itemsQuery($projectId, $data)->count();

        $total = $foldersTotal + $itemsTotal;
        $lastPage = max(1, (int)ceil($total / $data->perPage));
        $offset = ($data->page - 1) * $data->perPage;

        $folderOffset = min($offset, $foldersTotal);
        $folderTake = max(0, min($data->perPage, $foldersTotal - $folderOffset));
        $itemOffset = max(0, $offset - $foldersTotal);
        $itemTake = $data->perPage - $folderTake;

        $folderRows = $folderTake > 0
            ? $this->loadFolders($projectId, $data, $folderOffset, $folderTake)
            : [];

        $itemRows = $itemTake > 0
            ? $this->loadItems($projectId, $data, $itemOffset, $itemTake)
            : [];

        return [
            'data' => [...$folderRows, ...$itemRows],
            'pagination' => [
                'current_page' => $data->page,
                'last_page' => $lastPage,
                'per_page' => $data->perPage,
                'total' => $total,
                'folders_total' => $foldersTotal,
                'items_total' => $itemsTotal,
            ],
        ];
    }

    /** @return Builder<Category> */
    private function foldersQuery(?string $projectId, DirectoryFeedData $data): Builder
    {
        return Category::query()
            ->when($data->parentSet && $data->parentId === null, static fn(Builder $q) => $q->whereNull('parent_id'))
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn(Builder $q) => $q->where('parent_id', $data->parentId)
            )
            ->whereExists(static function (\Illuminate\Database\Query\Builder $q) use ($projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', Directory::class);
                if ($projectId !== null) {
                    $q->where('model_has_categories.project_id', $projectId);
                }
            })
            ->when(
                $data->search !== null,
                static fn(Builder $q) => $q->whereRaw(
                    'LOWER(categories.name) like ?',
                    ['%'.mb_strtolower((string)$data->search).'%']
                ),
            )
            ->orderBy('name');
    }

    /** @return Builder<Directory> */
    private function itemsQuery(?string $projectId, DirectoryFeedData $data): Builder
    {
        return Directory::query()
            ->when($projectId !== null, static fn(Builder $q) => $q->where('project_id', $projectId))
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn(Builder $q) => $q->whereHas(
                    'categories',
                    static fn(Builder $sub) => $sub->where('categories.id', $data->parentId),
                ),
            )
            ->when(
                $data->parentSet && $data->parentId === null,
                static fn(Builder $q) => $q->whereDoesntHave('categories'),
            )
            ->when(
                $data->search !== null,
                static function (Builder $q) use ($data): void {
                    $like = '%'.mb_strtolower((string)$data->search).'%';
                    $q->where(static function (Builder $w) use ($like): void {
                        $w->whereRaw('LOWER(directories.name) like ?', [$like])
                            ->orWhereRaw('LOWER(directories.slug) like ?', [$like]);
                    });
                },
            )
            ->orderBy('name');
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFolders(?string $projectId, DirectoryFeedData $data, int $offset, int $take): array
    {
        $rows = $this->foldersQuery($projectId, $data)
            ->withCount([
                'children' => static function (Builder $q) use ($projectId): void {
                    $q->whereExists(static function (\Illuminate\Database\Query\Builder $sub) use ($projectId): void {
                        $sub->from('model_has_categories')
                            ->whereColumn('model_has_categories.category_id', 'categories.id')
                            ->where('model_has_categories.model_type', Directory::class);
                        if ($projectId !== null) {
                            $sub->where('model_has_categories.project_id', $projectId);
                        }
                    });
                }
            ])
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(fn(Category $c): array => [
            'type' => 'folder',
            'id' => $c->id,
            'name' => $c->name,
            'parent_id' => $c->parent_id,
            'children_count' => $c->children_count ?? 0,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadItems(?string $projectId, DirectoryFeedData $data, int $offset, int $take): array
    {
        $rows = $this->itemsQuery($projectId, $data)
            ->withCount('versions')
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(fn(Directory $d): array => [
            'type' => 'directory',
            'id' => $d->id,
            'name' => $d->name,
            'slug' => $d->slug,
            'description' => $d->description,
            'source_type' => $d->source_type ?? 'manual',
            'sync_status' => $d->sync_status ?? 'idle',
            'versions_count' => $d->versions_count ?? 0,
            'created_at' => $d->created_at?->toIso8601String(),
            'updated_at' => $d->updated_at?->toIso8601String(),
        ])->values()->all();
    }
}
