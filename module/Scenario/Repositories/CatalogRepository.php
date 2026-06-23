<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Scenario\DTO\CatalogItemRow;
use Module\Scenario\QueryBuilders\CategoryBuilder;

final class CatalogRepository
{
    /**
     * @param  list<string>|null  $visibleByGroupIds
     * @return LengthAwarePaginator<int, CatalogItemRow>
     */
    public function paginate(
        ?string $parentId,
        ?string $query = null,
        bool $hasParentFilter = false,
        ?array $visibleByGroupIds = null
    ): LengthAwarePaginator {
        $builder = CategoryBuilder::query()
            ->activeOnly()
            ->search($query)
            ->visibleByGroups($visibleByGroupIds);

        $hasSearch = $query !== null && $query !== '';

        if ($hasSearch && $parentId !== null && $hasParentFilter) {
            $builder->filterByParentTree($parentId);

            return $builder->paginate();
        }

        if (!$hasSearch || $hasParentFilter) {
            $builder->filterByParentId($parentId);
        }

        return $builder->paginate();
    }
}
