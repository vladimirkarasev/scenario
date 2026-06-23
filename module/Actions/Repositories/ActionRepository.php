<?php

declare(strict_types=1);

namespace Module\Actions\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Module\Actions\DTO\ActionIndexData;
use Module\Actions\Models\Action;

final class ActionRepository
{
    /** @return LengthAwarePaginator<int, Action> */
    public function paginate(ActionIndexData $filters): LengthAwarePaginator
    {
        return Action::query()
            ->with([
                'schedule',
                'runs' => static fn(Relation $query): Relation => $query->latest()->limit(10),
            ])
            ->when($filters->search !== null, static function (Builder $query) use ($filters): void {
                $query->where(static function (Builder $query) use ($filters): void {
                    $query
                        ->where('name', 'like', "%{$filters->search}%")
                        ->orWhere('key', 'like', "%{$filters->search}%");
                });
            })
            ->when($filters->type !== null, static fn(Builder $query): Builder => $query->where('type', $filters->type))
            ->when(
                $filters->isActive !== null,
                static fn(Builder $query): Builder => $query->where('is_active', $filters->isActive)
            )
            ->orderBy('name')
            ->paginate($filters->perPage, ['*'], 'page[number]');
    }

    /** @return Collection<int, Action> */
    public function orderedWithRecentRuns(): Collection
    {
        return Action::query()
            ->with([
                'schedule',
                'runs' => static fn(Relation $query): Relation => $query->latest()->limit(10),
            ])
            ->orderBy('name')
            ->get();
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Action
    {
        return Action::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Action $action, array $attributes): Action
    {
        $action->fill($attributes);
        $action->save();

        return $action;
    }

    public function delete(Action $action): void
    {
        $action->delete();
    }

    public function loadRecentRuns(Action $action): Action
    {
        return $action->load([
            'schedule',
            'runs' => static fn(Relation $query): Relation => $query->latest()->limit(10),
        ]);
    }
}
