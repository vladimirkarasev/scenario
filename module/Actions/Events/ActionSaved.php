<?php

declare(strict_types=1);

namespace Module\Actions\Events;

/**
 * Экшен сохранён (create/update). Точка расширения: на это событие можно
 * подключать сколько угодно слушателей/подписчиков ({@see \Module\Actions\Listeners\SyncActionToApiListener} и др.).
 */
final readonly class ActionSaved
{
    public function __construct(
        public string $actionId,
    ) {}
}
