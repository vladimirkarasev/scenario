<?php

declare(strict_types=1);

namespace Module\Actions\Observers;

use Illuminate\Contracts\Events\Dispatcher;
use Module\Actions\Events\ActionSaved;
use Module\Actions\Models\Action;

/**
 * Единственный хук жизненного цикла модели Action. При сохранении (create/update)
 * бросает доменное событие {@see ActionSaved} — реакции добавляются слушателями/подписчиками.
 *
 * Обсёрвер резолвится через контейнер, поэтому зависимости — через конструктор (DI).
 * saved() покрывает и создание, и обновление; saveQuietly() события не вызывает.
 */
final readonly class ActionObserver
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function saved(Action $action): void
    {
        $this->events->dispatch(new ActionSaved($action->id));
    }
}
