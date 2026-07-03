<?php

declare(strict_types=1);

namespace Module\Scenario\Support;

use Module\Users\Models\User;

final readonly class UserGroupVisibility
{
    /**
     * Id активных групп пользователя в его проекте для фильтра видимости сценариев.
     * null — фильтр не применяется (admin с scenario_view_all); [] — ничего не видно.
     *
     * @return list<string>|null
     */
    public static function groupIds(?User $user): ?array
    {
        if ($user === null) {
            return [];
        }

        if ($user->can('scenario_view_all')) {
            return null;
        }

        /** @var list<string> */
        return $user->groups()
            ->where('user_groups.site_id', $user->project_id)
            ->where('user_groups.is_active', true)
            ->pluck('user_groups.id')
            ->map(static fn(mixed $v): string => is_string($v) ? $v : '')
            ->filter(static fn(string $v): bool => $v !== '')
            ->values()
            ->all();
    }
}
