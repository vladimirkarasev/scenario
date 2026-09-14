<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Catalog;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Scenario\DTO\CatalogIndexData;
use Module\Scenario\DTO\CatalogItemRow;
use Module\Scenario\Repositories\CatalogRepository;
use Module\Scenario\Support\UserGroupVisibility;
use Module\Users\Models\User;

final readonly class CatalogService
{
    public function __construct(private CatalogRepository $repository)
    {
    }

    /** @return LengthAwarePaginator<int, CatalogItemRow> */
    public function paginate(CatalogIndexData $data, ?User $user): LengthAwarePaginator
    {
        return $this->repository->paginate(
            parentId: $data->parentId,
            query: $data->query,
            hasParentFilter: $data->hasParentFilter,
            visibleByGroupIds: UserGroupVisibility::groupIds($user),
        );
    }
}
