<?php

declare(strict_types=1);

namespace Module\Users\Events;

use Module\Users\Models\User;

final readonly class UserCreated
{
    /**
     * @param  string[]  $roles
     * @param  string[]  $groupIds
     */
    public function __construct(
        public User $user,
        public array $roles,
        public array $groupIds,
        public int|string|null $actorId,
        public string $projectId,
    ) {
    }
}
