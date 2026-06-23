<?php

declare(strict_types=1);

namespace App\Queries\Scenario;

use Illuminate\Support\Collection;
use Module\Scenario\DTO\CatalogItemRow;
use Module\Scenario\QueryBuilders\CategoryBuilder;

final class CatalogItemsQueryBuilder
{
    /**
     * @param  Collection<int, string>|null      $activeCategoryIds
     * @return Collection<int, CatalogItemRow>
     */
    public function get(?string $folderId, ?Collection $activeCategoryIds = null): Collection
    {
        return CategoryBuilder::query()
            ->activeOnly($activeCategoryIds !== null)
            ->activeCategoryIds($activeCategoryIds)
            ->filterByParentId($folderId)
            ->get();
    }
}
