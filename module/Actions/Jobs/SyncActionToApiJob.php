<?php

declare(strict_types=1);

namespace Module\Actions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionService;

final class SyncActionToApiJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $actionId)
    {
    }

    public function handle(ActionService $actions): void
    {
        $url = config('services.action_sync.url');

        if (! is_string($url) || $url === '') {
            return;
        }

        $action = Action::query()->find($this->actionId);

        if ($action === null) {
            return;
        }

        Http::asJson()
            ->timeout(10)
            ->post($url, $actions->payload($action))
            ->throw();
    }
}
