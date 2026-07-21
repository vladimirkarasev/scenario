<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Module\Actions\DTO\ActionFeedData;
use Module\Actions\Enums\ActionType;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;

final readonly class ActionFeedService
{
    /**
     * @return array{
     *     data: array<int, array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, per_page: int, total: int, folders_total: int, items_total: int}
     * }
     */
    public function feed(?string $projectId, ActionFeedData $data): array
    {
        $foldersTotal = $this->foldersQuery($projectId, $data)->count();
        $itemsTotal = $this->itemsQuery($data)->count();

        $total = $foldersTotal + $itemsTotal;
        $lastPage = max(1, (int) ceil($total / $data->perPage));
        $offset = ($data->page - 1) * $data->perPage;

        $folderOffset = min($offset, $foldersTotal);
        $folderTake = max(0, min($data->perPage, $foldersTotal - $folderOffset));
        $itemOffset = max(0, $offset - $foldersTotal);
        $itemTake = $data->perPage - $folderTake;

        $folderRows = $folderTake > 0 ? $this->loadFolders($projectId, $data, $folderOffset, $folderTake) : [];
        $itemRows = $itemTake > 0 ? $this->loadItems($data, $itemOffset, $itemTake) : [];

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
    private function foldersQuery(?string $projectId, ActionFeedData $data): Builder
    {
        return Category::query()
            ->when($data->parentSet && $data->parentId === null, static fn (Builder $q) => $q->whereNull('parent_id'))
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn (Builder $q) => $q->where('parent_id', $data->parentId)
            )
            ->whereExists(static function (\Illuminate\Database\Query\Builder $q) use ($projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', Action::class);
                if ($projectId !== null) {
                    $q->where('model_has_categories.project_id', $projectId);
                }
            })
            ->when(
                $data->search !== null,
                static fn (Builder $q) => $q->whereRaw(
                    'LOWER(categories.name) like ?',
                    ['%'.mb_strtolower((string) $data->search).'%']
                ),
            )
            ->orderBy('name');
    }

    /** @return Builder<Action> */
    private function itemsQuery(ActionFeedData $data): Builder
    {
        return Action::query()
            ->when(
                $data->parentSet && $data->parentId !== null,
                static fn (Builder $q) => $q->whereHas(
                    'categories',
                    static fn (Builder $sub) => $sub->where('categories.id', $data->parentId),
                ),
            )
            ->when(
                $data->parentSet && $data->parentId === null,
                static fn (Builder $q) => $q->whereDoesntHave('categories'),
            )
            ->when(
                $data->search !== null,
                static function (Builder $q) use ($data): void {
                    $like = '%'.mb_strtolower((string) $data->search).'%';
                    $q->where(static function (Builder $w) use ($like): void {
                        $w->whereRaw('LOWER(actions.name) like ?', [$like])
                            ->orWhereRaw('LOWER(actions.slug) like ?', [$like]);
                    });
                },
            )
            ->orderBy('name');
    }

    /** @return array<int, array<string, mixed>> */
    private function loadFolders(?string $projectId, ActionFeedData $data, int $offset, int $take): array
    {
        $rows = $this->foldersQuery($projectId, $data)
            ->withCount([
                'children' => static function (Builder $q) use ($projectId): void {
                    $q->whereExists(static function (\Illuminate\Database\Query\Builder $sub) use ($projectId): void {
                        $sub->from('model_has_categories')
                            ->whereColumn('model_has_categories.category_id', 'categories.id')
                            ->where('model_has_categories.model_type', Action::class);
                        if ($projectId !== null) {
                            $sub->where('model_has_categories.project_id', $projectId);
                        }
                    });
                },
            ])
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(fn (Category $c): array => [
            'type' => 'folder',
            'id' => $c->id,
            'name' => $c->name,
            'parent_id' => $c->parent_id,
            'is_system' => $c->is_system,
            'children_count' => $c->children_count ?? 0,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadItems(ActionFeedData $data, int $offset, int $take): array
    {
        $rows = $this->itemsQuery($data)
            ->with('schedule')
            ->offset($offset)
            ->limit($take)
            ->get();

        return $rows->map(function (Action $a): array {
            $schedule = $a->schedule instanceof ActionSchedule ? $a->schedule : null;

            return [
                'type' => 'action',
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'action_type' => $a->type,
                'action_type_label' => ActionType::tryFrom($a->type)?->label() ?? $a->type,
                'is_active' => $a->is_active,
                'description' => $a->description,
                'schedule' => $schedule !== null ? [
                    'enabled' => $schedule->enabled,
                    'cron' => $schedule->cron,
                    'next_run_at' => $schedule->next_run_at?->toIso8601String(),
                ] : null,
                'created_at' => $a->created_at?->toIso8601String(),
                'updated_at' => $a->updated_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
