<?php

declare(strict_types=1);

namespace Module\Directories\Listeners;

use Illuminate\Support\Facades\Log;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Events\DirectoryImportStatusUpdated;

final readonly class LogDirectoryImportStatusUpdate
{
    public function handle(DirectoryImportStatusUpdated $event): void
    {
        if ($event->status === DirectoryImportStatus::Failed->value) {
            Log::error(
                'Directory import failed.',
                $event->logContext() + [
                    'error_message' => $event->errorMessage,
                ]
            );

            return;
        }

        Log::info('Directory import status updated.', $event->logContext());
    }
}
