<?php

declare(strict_types=1);

namespace Module\Users\Events;

final readonly class SecurityEvent
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $action,
        public int|string|null $actorId,
        public array $context,
    ) {
    }
}
