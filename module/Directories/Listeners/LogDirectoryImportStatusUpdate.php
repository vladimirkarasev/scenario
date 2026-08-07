<?php

declare(strict_types=1);

namespace Module\Directories\Listeners;

use Illuminate\Support\Facades\Log;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Psr\Log\LoggerInterface;

final readonly class LogDirectoryImportStatusUpdate
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(DirectoryImportStatusUpdated $event): void
    {
        if ($event->status === DirectoryImportStatus::Failed->value) {
            $this->logger->error('Directory import failed.', [
                $event->logContext() + [
                    'error_message' => $event->errorMessage,
                ]
            ]);

            return;
        }

        $this->logger->info('Directory import status updated.', $event->logContext());
    }
}
