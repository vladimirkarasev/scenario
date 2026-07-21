<?php

declare(strict_types=1);

namespace Module\Users\Listeners;

use Module\Users\Events\SecurityEvent;
use Psr\Log\LoggerInterface;

final readonly class LogSecurityAudit
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(SecurityEvent $event): void
    {
        try {
            $this->logger->info($event->action, [
                ...$event->context,
                'actor' => $event->actorId,
            ]);
        } catch (\Throwable) {
        }
    }
}
