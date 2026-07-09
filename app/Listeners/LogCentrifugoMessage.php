<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CentrifugoMessagePublished;
use Illuminate\Support\Facades\Log;

final readonly class LogCentrifugoMessage
{
    public function handle(CentrifugoMessagePublished $event): void
    {
        Log::info('Centrifugo message published.', [
            'channel' => $event->channel,
            'type' => $event->payload['type'] ?? null,
        ]);
    }
}
