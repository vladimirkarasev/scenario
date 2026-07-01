<?php

declare(strict_types=1);

namespace Module\Users\Listeners;

use Module\Users\Events\UserCreated;
use Module\Users\Events\UserDeleted;
use Module\Users\Events\UserUpdated;
use Psr\Log\LoggerInterface;

final readonly class LogUserAudit
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function created(UserCreated $event): void
    {
        $this->write('user.created', [
            'user_id' => $event->user->id,
            'roles' => $event->roles,
            'groups' => $event->groupIds,
            'actor' => $event->actorId,
            'project_id' => $event->projectId,
        ]);
    }

    public function updated(UserUpdated $event): void
    {
        $this->write('user.updated', [
            'user_id' => $event->user->id,
            'roles' => $event->roles,
            'groups' => $event->groupIds,
            'actor' => $event->actorId,
            'project_id' => $event->projectId,
        ]);
    }

    public function deleted(UserDeleted $event): void
    {
        $this->write('user.deleted', [
            'user_id' => $event->userId,
            'project_id' => $event->projectId,
            'actor' => $event->actorId,
        ]);
    }

    /** @param  array<string, mixed>  $context */
    private function write(string $event, array $context): void
    {
        try {
            $this->logger->info($event, $context);
        } catch (\Throwable) {
            // Аудит не должен превращать уже выполненную операцию в ошибку HTTP.
        }
    }
}
