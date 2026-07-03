<?php

declare(strict_types=1);

namespace Module\Scenario\QueryBuilders;

use Module\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Module\Scenario\Enums\ScenarioStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Support\UserGroupVisibility;

/**
 * @extends Builder<Scenario>
 */
final class ScenarioBuilder extends Builder
{
    public function activeOnly(bool $activeOnly = true): self
    {
        if (!$activeOnly) {
            return $this;
        }

        return $this->whereHas('activeVersion', static function (Builder $builder): void {
            $builder->where('status', 'active');
        });
    }

    public function search(?string $query): self
    {
        if ($query === null || $query === '') {
            return $this;
        }

        $like = '%'.mb_strtolower($query).'%';

        return $this->where(static function (Builder $builder) use ($like): void {
            $builder
                ->whereRaw('LOWER(scenarios.name) like ?', [$like])
                ->orWhereRaw('LOWER(scenarios.description) like ?', [$like])
                ->orWhereRaw('LOWER(scenarios.alias) like ?', [$like]);
        });
    }

    public function isActive(?bool $isActive): self
    {
        if ($isActive === null) {
            return $this;
        }

        return $this->where('scenarios.is_active', $isActive);
    }

    public function status(?ScenarioStatus $status): self
    {
        if ($status === null) {
            return $this;
        }

        return $this->where('scenarios.status', $status->value);
    }

    /** @param  list<string>|null  $tags */
    public function tags(?array $tags): self
    {
        if (empty($tags)) {
            return $this;
        }

        return $this->where(static function (Builder $builder) use ($tags): void {
            foreach ($tags as $tag) {
                $builder->orWhereJsonContains('scenarios.tags', $tag);
            }
        });
    }

    public function categoryId(?string $categoryId): self
    {
        if ($categoryId === null || $categoryId === '') {
            return $this;
        }

        if ($categoryId === 'null') {
            return $this->whereNotExists(static function (\Illuminate\Database\Query\Builder $builder): void {
                $builder
                    ->selectRaw('1')
                    ->from('model_has_categories')
                    ->whereColumn('model_has_categories.model_id', 'scenarios.id')
                    ->where('model_has_categories.model_type', Scenario::class);
            });
        }

        return $this->whereExists(
            static function (\Illuminate\Database\Query\Builder $builder) use ($categoryId): void {
                $builder
                    ->selectRaw('1')
                    ->from('model_has_categories')
                    ->whereColumn('model_has_categories.model_id', 'scenarios.id')
                    ->where('model_has_categories.category_id', $categoryId)
                    ->where('model_has_categories.model_type', Scenario::class);
            }
        );
    }

    public function forProject(string $projectId): self
    {
        return $this->where('scenarios.project_id', $projectId);
    }

    public function forAlias(string $alias): self
    {
        return $this->where('scenarios.alias', $alias);
    }

    public function hasActiveVersion(): self
    {
        return $this->whereNotNull('scenarios.active_version_id');
    }

    /** @param  string[]|null  $ids */
    public function categoryIds(?array $ids): self
    {
        if (empty($ids)) {
            return $this;
        }

        return $this->whereExists(static function (\Illuminate\Database\Query\Builder $builder) use ($ids): void {
            $builder
                ->selectRaw('1')
                ->from('model_has_categories')
                ->whereColumn('model_has_categories.model_id', 'scenarios.id')
                ->whereIn('model_has_categories.category_id', $ids)
                ->where('model_has_categories.model_type', Scenario::class);
        });
    }

    /**
     * Только сценарии, у которых есть хотя бы одна группа из переданного списка.
     * Пустой список → ничего не видно.
     *
     * @param  list<string>  $groupIds
     */
    public function visibleByGroups(array $groupIds): self
    {
        if (empty($groupIds)) {
            return $this->whereRaw('1 = 0');
        }

        return $this->whereExists(static function (\Illuminate\Database\Query\Builder $builder) use ($groupIds): void {
            $builder
                ->selectRaw('1')
                ->from('model_has_groups')
                ->whereColumn('model_has_groups.model_id', 'scenarios.id')
                ->whereIn('model_has_groups.group_id', $groupIds)
                ->where('model_has_groups.model_type', Scenario::class);
        });
    }

    /**
     * Фильтр для пользовательского workspace.
     * Админ (с пермишн scenario_view_all) видит всё; остальные — только сценарии в своих группах.
     */
    public function visibleForUser(?User $user): self
    {
        $groupIds = UserGroupVisibility::groupIds($user);

        return $groupIds === null ? $this : $this->visibleByGroups($groupIds);
    }
}
