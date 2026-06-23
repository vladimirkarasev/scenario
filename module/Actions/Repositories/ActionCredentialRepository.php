<?php

declare(strict_types=1);

namespace Module\Actions\Repositories;

use Illuminate\Support\Collection;
use Module\Actions\Models\ActionCredential;

final class ActionCredentialRepository
{
    /** @return Collection<int, ActionCredential> */
    public function orderedByName(): Collection
    {
        return ActionCredential::query()
            ->orderBy('name')
            ->get();
    }

    public function find(?int $id): ?ActionCredential
    {
        if ($id === null) {
            return null;
        }

        return ActionCredential::query()->find($id);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): ActionCredential
    {
        return ActionCredential::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(ActionCredential $credential, array $attributes): ActionCredential
    {
        $credential->fill($attributes);
        $credential->save();

        return $credential;
    }

    public function delete(ActionCredential $credential): void
    {
        $credential->delete();
    }
}
