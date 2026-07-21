<?php

declare(strict_types=1);

namespace Module\Actions\Observers;

use Illuminate\Contracts\Events\Dispatcher;
use Module\Actions\Events\ActionSaved;
use Module\Actions\Models\Action;

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
