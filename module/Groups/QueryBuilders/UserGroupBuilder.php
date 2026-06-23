<?php

declare(strict_types=1);

namespace Module\Groups\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Module\Groups\Models\UserGroup;

/**
 * @extends Builder<UserGroup>
 */
final class UserGroupBuilder extends Builder
{
    public function forProject(?string $projectId): static
    {
        if ($projectId === null) {
            return $this;
        }

        return $this->where('site_id', $projectId);
    }

    public function search(?string $value): static
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->where(static function (Builder $q) use ($value): void {
            $q->where('name', 'like', "%{$value}%")
                ->orWhere('slug', 'like', "%{$value}%");
        });
    }

    public function active(?bool $value): static
    {
        if ($value === null) {
            return $this;
        }

        return $this->where('is_active', $value);
    }
}
