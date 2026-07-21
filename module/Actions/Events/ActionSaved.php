<?php

declare(strict_types=1);

namespace Module\Actions\Events;

final readonly class ActionSaved
{
    public function __construct(
        public string $actionId,
    ) {}
}
