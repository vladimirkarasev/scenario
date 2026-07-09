<?php

declare(strict_types=1);

namespace Module\Proxy\Repositories;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Proxy\DTO\ProxyEndpointIndexData;
use Module\Proxy\Models\ProxyEndpoint;

final class ProxyEndpointRepository
{
    /** @return LengthAwarePaginator<int, ProxyEndpoint> */
    public function paginate(ProxyEndpointIndexData $data, string $projectId): LengthAwarePaginator
    {
        $query = ProxyEndpoint::query()
            ->with(['categories', 'connection'])
            ->where('project_id', $projectId)
            ->when($data->type !== null, static fn (Builder $q) => $q->where('type', $data->type))
            ->when($data->categoryIds !== [], static function (Builder $q) use ($data): void {
                $q->whereHas(
                    'categories',
                    static fn (Builder $category) => $category->whereIn('categories.id', $data->categoryIds),
                );
            })
            ->when($data->search !== null, static function (Builder $q) use ($data): void {
                $like = '%'.mb_strtolower((string) $data->search).'%';
                $q->where(static function (Builder $search) use ($like): void {
                    $search->whereRaw('LOWER(name) like ?', [$like])
                        ->orWhereRaw('LOWER(code) like ?', [$like]);
                });
            });

        $this->applySort($query, $data->sort);

        return $query->paginate(
            $data->pagination->size,
            ['*'],
            'page[number]',
            $data->pagination->number,
        );
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ProxyEndpoint
    {
        return ProxyEndpoint::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(ProxyEndpoint $endpoint, array $attributes): ProxyEndpoint
    {
        $endpoint->update($attributes);

        return $endpoint;
    }

    public function delete(ProxyEndpoint $endpoint): void
    {
        $endpoint->delete();
    }

    /** @param list<string> $sort */
    private function applySort(Builder $query, array $sort): void
    {
        $allowed = ['name', 'code', 'type', 'is_active', 'created_at', 'updated_at'];
        foreach ($sort as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');
            if (in_array($column, $allowed, true)) {
                $query->orderBy($column, $direction);
            }
        }
    }
}
