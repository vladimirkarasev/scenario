<?php

declare(strict_types=1);

namespace Module\Users\Events;

final readonly class UserDeleted
{
    public function __construct(
        public int $userId,
        public string $projectId,
        public int|string|null $actorId,
    ) {
    }
}
