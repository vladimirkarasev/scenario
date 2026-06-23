<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Scenario\DTO\CatalogItemRow;
use Module\Scenario\Repositories\CatalogRepository;

final readonly class CatalogService
{
    public function __construct(private CatalogRepository $repository) {}

    /** @return LengthAwarePaginator<int, CatalogItemRow> */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return $this->repository->paginate(
            parentId: $this->parentId($request),
            query: $this->filter($request, 'q'),
            hasParentFilter: $this->hasFilter($request, 'parent_id'),
            visibleByGroupIds: $this->visibleByGroupIds($request),
        );
    }

    /** @return list<string>|null  null = admin/bypass; [] = пустой список групп у пользователя */
    private function visibleByGroupIds(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return [];
        }
        if ($user->can('scenario_view_all')) {
            return null;
        }

        /** @var list<string> */
        return $user->groups->pluck('id')
            ->map(static fn (mixed $v): string => is_string($v) ? $v : '')
            ->filter(static fn (string $v): bool => $v !== '')
            ->values()
            ->all();
    }

    private function parentId(Request $request): ?string
    {
        return $this->filter($request, 'parent_id');
    }

    private function filter(Request $request, string $key): ?string
    {
        $value = $request->input("filter.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hasFilter(Request $request, string $key): bool
    {
        $filter = $request->query('filter');

        return is_array($filter) && array_key_exists($key, $filter);
    }
}
