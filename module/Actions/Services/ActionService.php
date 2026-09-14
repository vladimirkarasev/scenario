<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use App\Exceptions\ForbiddenException;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Actions\DTO\ActionData;
use Module\Actions\Enums\ActionErrorCode;
use Module\Actions\DTO\ActionIndexData;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionRun;
use Module\Actions\Repositories\ActionRepository;
use Module\Projects\CurrentProject;

final readonly class ActionService
{
    public function __construct(
        private ActionRepository $actions,
        private CurrentProject $currentProject,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function items(): array
    {
        return $this->actions
            ->orderedWithRecentRuns()
            ->map(fn (Action $action): array => $this->payload($action))
            ->values()
            ->all();
    }

    /** @return LengthAwarePaginator<int, Action> */
    public function paginate(ActionIndexData $filters): LengthAwarePaginator
    {
        return $this->actions->paginate($filters);
    }

    public function find(Action $action): Action
    {
        return $this->actions->loadRecentRuns($action);
    }

    public function create(ActionData $data): Action
    {
        $this->ensureManageAccess($data->canManageActions);

        $action = $this->actions->create($data->toAttributes());
        $action->categories()->sync($this->categoryPivot($data->categoryIds));

        return $this->actions->loadRecentRuns($action);
    }

    public function update(ActionData $data, Action $action): Action
    {
        $this->ensureManageAccess($data->canManageActions);

        $action = $this->actions->update($action, $data->toAttributes());
        $action->categories()->sync($this->categoryPivot($data->categoryIds));

        return $this->actions->loadRecentRuns($action);
    }

    public function delete(bool $canManageActions, Action $action): void
    {
        $this->ensureManageAccess($canManageActions);

        $this->actions->delete($action);
    }

    /** @return array<string, mixed> */
    public function payload(Action $action): array
    {
        return [
            'id' => $action->id,
            'name' => $action->name,
            'slug' => $action->slug,
            'code' => $action->code,
            'description' => $action->description,
            'type' => $action->type,
            'is_active' => $action->is_active,
            'config' => $action->config,
            'schema' => $action->schema,
            'ui_schema' => $action->ui_schema,
            'input_fields' => $action->input_fields ?? [],
            'credential_id' => $action->config['credential_id'] ?? null,
            'runs' => $action->relationLoaded('runs')
                ? $action->runs->map(fn (ActionRun $run): array => $this->runPayload($run))->values()->all()
                : [],
            'created_at' => $action->created_at?->toIso8601String(),
            'updated_at' => $action->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function runPayload(ActionRun $run): array
    {
        return [
            'id' => $run->id,
            'action_id' => $run->action_id,
            'status' => $run->status,
            'input' => $run->input,
            'output' => $run->output,
            'error' => $run->error,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'duration_ms' => $run->duration_ms,
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>                                  $categoryIds
     * @return array<string, array{project_id: string|null}>
     */
    private function categoryPivot(array $categoryIds): array
    {
        $projectId = $this->currentProject->id();

        $pivot = [];
        foreach ($categoryIds as $id) {
            $pivot[$id] = ['project_id' => $projectId];
        }

        return $pivot;
    }

    private function ensureManageAccess(bool $canManageActions): void
    {
        if (! $canManageActions) {
            throw ForbiddenException::from(ActionErrorCode::ManageForbidden);
        }
    }
}
