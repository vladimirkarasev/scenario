<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Actions\Observers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Module\Actions\Events\ActionSaved;
use Module\Actions\Models\Action;
use Tests\TestCase;

/**
 * ActionObserver на сохранении (create/update) бросает доменное событие ActionSaved.
 */
final class ActionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_action_dispatches_event(): void
    {
        Event::fake([ActionSaved::class]);

        $action = $this->makeAction('email-sync');

        Event::assertDispatched(
            ActionSaved::class,
            fn (ActionSaved $event): bool => $event->actionId === $action->id,
        );
    }

    public function test_updating_action_dispatches_event(): void
    {
        $action = $this->makeAction('crm-sync');

        Event::fake([ActionSaved::class]);
        $action->update(['name' => 'Renamed']);

        Event::assertDispatched(ActionSaved::class);
    }

    public function test_save_quietly_does_not_dispatch(): void
    {
        $action = $this->makeAction('quiet');

        Event::fake([ActionSaved::class]);
        $action->forceFill(['name' => 'Quiet'])->saveQuietly();

        Event::assertNotDispatched(ActionSaved::class);
    }

    private function makeAction(string $slug): Action
    {
        return Action::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'code' => str_replace('-', '_', $slug),
            'type' => 'template_file',
            'is_active' => true,
            'config' => [],
        ]);
    }
}
