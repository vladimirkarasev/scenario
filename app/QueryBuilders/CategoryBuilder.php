<?php

declare(strict_types=1);

namespace App\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @template TModelClass of \App\Models\Category
 *
 * @extends Builder<TModelClass>
 */
final class CategoryBuilder extends Builder
{
    /** @return $this */
    public function workspaceOnly(): static
    {
        $this->where('categories.is_workspace', true);

        return $this;
    }

    /** @return $this */
    public function excluding(string $categoryId): static
    {
        $this->where('categories.id', '!=', $categoryId);

        return $this;
    }

    /** @return $this */
    public function boundToModelType(
        string $modelType,
        ?string $projectId,
        bool $matchNullProject = false,
    ): static {
        $this->whereExists(
            static function (QueryBuilder $query) use ($modelType, $projectId, $matchNullProject): void {
                $query->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', $modelType);

                if ($projectId !== null) {
                    $query->where('model_has_categories.project_id', $projectId);
                } elseif ($matchNullProject) {
                    $query->whereNull('model_has_categories.project_id');
                }
            },
        );

        return $this;
    }

    /** @return $this */
    public function activeOnly(bool $activeOnly = true): static
    {
        if ($activeOnly) {
            $this->where('categories.is_active', true);
        }

        return $this;
    }

    /** @return $this */
    public function search(?string $q): static
    {
        if ($q === null || $q === '') {
            return $this;
        }

        $like = '%'.mb_strtolower($q).'%';

        $this->where(static function (Builder $builder) use ($like): void {
            $builder
                ->whereRaw('LOWER(categories.name) like ?', [$like]);
        });

        return $this;
    }

    /** @return $this */
    public function filterByParentId(?string $id): static
    {
        if ($id === null) {
            $this->whereNull('categories.parent_id');

            return $this;
        }

        $this->where('categories.parent_id', $id);

        return $this;
    }
}
