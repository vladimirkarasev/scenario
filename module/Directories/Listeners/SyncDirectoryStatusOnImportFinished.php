<?php

declare(strict_types=1);

namespace Module\Directories\Listeners;

use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DictionaryApiSyncService;

final readonly class SyncDirectoryStatusOnImportFinished
{
    public function __construct(
        private DictionaryApiSyncService $apiSync,
    ) {}

    public function handle(DirectoryImportStatusUpdated $event): void
    {
        if (!in_array($event->status, [DirectoryImportStatus::Completed->value, DirectoryImportStatus::Failed->value], true)) {
            return;
        }

        $directory = Directory::query()->find($event->directoryId);

        if ($directory === null || $directory->source_type !== 'api') {
            return;
        }

        if ($event->status === DirectoryImportStatus::Failed->value) {
            $directory->forceFill([
                'sync_status' => 'failed',
                'sync_error' => $event->errorMessage,
                'next_sync_at' => $this->apiSync->nextSyncAt($directory),
            ])->save();

            return;
        }

        $directory->forceFill([
            'last_sync_at' => now(),
            'next_sync_at' => $this->apiSync->nextSyncAt($directory),
            'sync_status' => 'success',
            'sync_error' => null,
        ])->save();
    }
}
