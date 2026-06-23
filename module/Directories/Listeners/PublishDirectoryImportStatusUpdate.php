<?php

declare(strict_types=1);

namespace Module\Directories\Listeners;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Support\Facades\Log;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Throwable;

final readonly class PublishDirectoryImportStatusUpdate
{
    public function __construct(private Centrifugo $centrifugo) {}

    public function handle(DirectoryImportStatusUpdated $event): void
    {
        try {
            $this->centrifugo->publish($event->channel(), $event->payload());
        } catch (Throwable $exception) {
            Log::warning('Failed to publish directory import status update.', $event->logContext() + [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
