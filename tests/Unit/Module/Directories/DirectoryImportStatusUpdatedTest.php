<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Directories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Module\Directories\Listeners\LogDirectoryImportStatusUpdate;
use Module\Directories\Listeners\PublishDirectoryImportStatusUpdate;
use Module\Directories\Models\DirectoryImport;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Tests\TestCase;

final class DirectoryImportStatusUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_contains_multi_file_progress_for_logs_and_centrifugo(): void
    {
        $import = new DirectoryImport([
            'directory_id' => 'directory-id',
            'directory_version_id' => 10,
            'status' => 'processing',
            'source_type' => 'file',
            'source_config_json' => [
                'files' => [
                    ['path' => 'first.xlsx'],
                    ['path' => 'second.xlsx'],
                ],
            ],
            'processed_rows' => 25,
            'imported_rows' => 24,
            'failed_rows' => 1,
        ]);
        $import->id = 42;

        $event = DirectoryImportStatusUpdated::fromImport($import);

        $this->assertSame('directory-import:42', $event->channel());
        $this->assertSame('file', $event->payload()['source_type']);
        $this->assertSame(2, $event->payload()['sources_total']);
        $this->assertSame(25, $event->payload()['processed_rows']);
        $this->assertSame(2, $event->logContext()['sources_total']);
    }

    public function test_listeners_log_and_publish_the_same_status_event(): void
    {
        Log::spy();
        $centrifugo = $this->createMock(CentrifugoApiInterface::class);
        $centrifugo
            ->expects($this->once())
            ->method('publish')
            ->with(
                'directory-import:42',
                $this->callback(static function (string $json): bool {
                    $payload = json_decode($json, true);

                    return $payload['status'] === 'completed'
                        && $payload['sources_total'] === 1;
                }),
            );
        $event = new DirectoryImportStatusUpdated(
            importId: 42,
            directoryId: 'directory-id',
            directoryVersionId: 10,
            status: 'completed',
            processedRows: 2,
            importedRows: 2,
            failedRows: 0,
            errorMessage: null,
            sourceType: 'proxy',
            sourcesTotal: 1,
        );

        (new LogDirectoryImportStatusUpdate())->handle($event);
        (new PublishDirectoryImportStatusUpdate($centrifugo))->handle($event);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Directory import status updated.', $event->logContext());
    }
}
