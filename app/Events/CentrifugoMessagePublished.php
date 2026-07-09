<?php

declare(strict_types=1);

namespace App\Events;

final readonly class CentrifugoMessagePublished
{
    /**
     * @param non-empty-string $channel
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $channel,
        public array $payload,
    ) {
    }
}
