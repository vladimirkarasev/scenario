<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Module\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ScenarioRunUserRepository
{
    /**
     * Поиск пользователей для фильтра прогонов: приоритет у явных id,
     * иначе поиск по строке; без критериев — пустой список.
     *
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public function lookup(array $ids, string $search, string $projectId): Collection
    {
        $query = User::query()
            ->forProject($projectId)
            ->orderBy('name')
            ->limit(30);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($search !== '') {
            $query->search($search);
        } else {
            $query->limit(0);
        }

        return $query->get();
    }
}
