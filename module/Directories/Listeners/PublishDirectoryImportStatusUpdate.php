<?php

declare(strict_types=1);

namespace Module\Directories\Listeners;

use Illuminate\Support\Facades\Log;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Throwable;

final readonly class PublishDirectoryImportStatusUpdate
{
    public function __construct(private CentrifugoApiInterface $centrifugo)
    {
    }

    public function handle(DirectoryImportStatusUpdated $event): void
    {
        try {
            $this->centrifugo->publish($event->channel(), json_encode($event->payload(), JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            Log::warning(
                'Failed to publish directory import status update.',
                $event->logContext() + [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]
            );
        }
    }
}
