<?php

declare(strict_types=1);

namespace Module\Scenario\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\ScenarioRun;

/**
 * @extends Builder<ScenarioRun>
 */
final class ScenarioRunBuilder extends Builder
{
    public function forScenario(?string $scenarioId): self
    {
        if ($scenarioId === null || $scenarioId === '') {
            return $this;
        }

        return $this->where('scenario_id', $scenarioId);
    }

    public function forProject(string $projectId): self
    {
        return $this->whereHas(
            'scenario',
            static fn(Builder $scenario): Builder => $scenario->where('project_id', $projectId),
        );
    }

    public function status(?ScenarioRunStatus $status): self
    {
        if ($status === null) {
            return $this;
        }

        return $this->where('status', $status);
    }

    /** Только завершённые прогоны (успешные и упавшие). */
    public function finished(bool $finished = true): self
    {
        if (!$finished) {
            return $this;
        }

        return $this->whereIn('status', [ScenarioRunStatus::Completed, ScenarioRunStatus::Failed]);
    }

    /** @param  list<int>  $ids */
    public function createdByAny(array $ids): self
    {
        if ($ids === []) {
            return $this;
        }

        return $this->whereIn('created_by', $ids);
    }

    /** Поиск по названию сценария или id прогона. */
    public function search(?string $query): self
    {
        $query = $query !== null ? trim($query) : '';
        if ($query === '') {
            return $this;
        }

        return $this->where(static function (Builder $builder) use ($query): void {
            $builder
                ->whereHas('scenario', static fn(Builder $scenario) => $scenario->where('name', 'like', "%{$query}%"))
                ->orWhere('id', 'like', "%{$query}%");
        });
    }

    public function createdFrom(?string $date): self
    {
        if ($date === null || $date === '') {
            return $this;
        }

        return $this->whereDate('created_at', '>=', $date);
    }

    public function createdTo(?string $date): self
    {
        if ($date === null || $date === '') {
            return $this;
        }

        return $this->whereDate('created_at', '<=', $date);
    }

    public function withListRelations(): self
    {
        return $this->with(['scenario', 'version', 'createdBy', 'updatedBy']);
    }

    public function withPlayerRelations(): self
    {
        return $this->with(['scenario', 'version', 'revision', 'steps', 'operator.project']);
    }
}
