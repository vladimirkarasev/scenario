<?php

declare(strict_types=1);

namespace App\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModelClass of \App\Models\Category
 *
 * @extends Builder<TModelClass>
 */
final class CategoryBuilder extends Builder
{
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
