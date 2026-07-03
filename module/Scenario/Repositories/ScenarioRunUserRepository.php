<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Module\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ScenarioRunUserRepository
{
    /**
     * Поиск пользователей для фильтра прогонов: приоритет у явных id,
     * иначе поиск по строке; без критериев — пустой список.
     *
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public function lookup(array $ids, string $search): Collection
    {
        $query = User::query()->orderBy('name')->limit(30);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($search !== '') {
            $query->search($search);
        } else {
            $query->limit(0);
        }

        return $query->get();
    }

    /** @param  array<string, mixed>  $userData */
    public function firstOrCreateFromRunData(array $userData): User
    {
        $email = isset($userData['email']) && is_string($userData['email']) && $userData['email'] !== ''
            ? $userData['email']
            : 'auto_'.Str::uuid().'@scenario.local';

        $name = isset($userData['name']) && is_string($userData['name']) && $userData['name'] !== ''
            ? $userData['name']
            : explode('@', $email)[0];

        $fio = isset($userData['fio']) && is_string($userData['fio']) ? $userData['fio'] : null;

        return User::query()->firstOrCreate(
            ['email' => $email],
            array_filter([
                'name' => $name,
                'fio' => $fio,
                'password' => Hash::make(Str::random(32)),
            ], static fn(mixed $value): bool => $value !== null),
        );
    }
}
