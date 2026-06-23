<?php

declare(strict_types=1);

namespace Module\Actions\Repositories;

use Illuminate\Support\Collection;
use Module\Actions\DTO\ActionRunIndexData;
use Module\Actions\Models\ActionRun;

final class ActionRunRepository
{
    /** @return Collection<int, ActionRun> */
    public function latestWithAction(ActionRunIndexData $filters): Collection
    {
        return ActionRun::query()
            ->with('action')
            ->when($filters->status !== null, static fn($query): mixed => $query->where('status', $filters->status))
            ->when(
                $filters->actionId !== null,
                static fn($query): mixed => $query->where('action_id', $filters->actionId)
            )
            ->latest()
            ->limit($filters->limit)
            ->get();
    }
}
