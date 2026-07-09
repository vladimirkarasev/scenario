<?php

declare(strict_types=1);

namespace Module\Proxy\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Proxy\DTO\ProxyConnectionIndexData;
use Module\Proxy\Models\ProxyConnection;

final class ProxyConnectionRepository
{
    /** @return LengthAwarePaginator<int, ProxyConnection> */
    public function paginate(ProxyConnectionIndexData $data, string $projectId): LengthAwarePaginator
    {
        $query = ProxyConnection::query()
            ->where('project_id', $projectId)
            ->when(
                $data->credentialType !== null,
                static fn (Builder $builder) => $builder->where('credential_type', $data->credentialType),
            )
            ->when($data->search !== null, static function (Builder $builder) use ($data): void {
                $like = '%'.mb_strtolower((string) $data->search).'%';
                $builder->whereRaw('LOWER(name) like ?', [$like]);
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
    public function create(array $attributes): ProxyConnection
    {
        return ProxyConnection::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(ProxyConnection $connection, array $attributes): ProxyConnection
    {
        $connection->update($attributes);

        return $connection;
    }

    public function delete(ProxyConnection $connection): void
    {
        $connection->delete();
    }

    /**
     * @param Builder<ProxyConnection> $query
     * @param list<string> $sort
     */
    private function applySort(Builder $query, array $sort): void
    {
        $allowed = ['name', 'credential_type', 'created_at', 'updated_at'];
        foreach ($sort as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');
            if (in_array($column, $allowed, true)) {
                $query->orderBy($column, $direction);
            }
        }
    }
}
