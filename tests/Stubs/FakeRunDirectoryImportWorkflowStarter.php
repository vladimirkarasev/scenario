<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Module\Directories\Temporal\Activities\FetchDirectoryImportPageActivity;
use Module\Directories\Temporal\Activities\FinalizeDirectoryImportActivity;
use Module\Directories\Temporal\Activities\ImportDirectoryExcelChunkActivity;
use Module\Directories\Temporal\Activities\PrepareDirectoryImportActivity;
use Module\Directories\Temporal\RunDirectoryImportWorkflowInput;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use Throwable;

/**
 * Records Temporal workflow-start calls like the other Fake*WorkflowStarter stubs. When
 * `$runInline` is enabled, it also synchronously replays the same Prepare/Chunk|Page/Finalize
 * activity sequence the real Workflow would run — needed because tests have no live Temporal
 * worker to actually execute the workflow, but still want to assert on the resulting DirectoryImport/DirectoryItem state.
 */
final class FakeRunDirectoryImportWorkflowStarter implements RunDirectoryImportWorkflowStarterInterface
{
    /** @var list<RunDirectoryImportWorkflowInput> */
    public array $calls = [];

    /** @var list<RunDirectoryImportWorkflowInput> */
    public array $pagedCalls = [];

    public function __construct(
        public bool $runInline = false,
    ) {
    }

    public function start(RunDirectoryImportWorkflowInput $input): void
    {
        $this->calls[] = $input;

        if ($this->runInline) {
            $this->runChunked($input->directoryImportId);
        }
    }

    public function startPaged(RunDirectoryImportWorkflowInput $input): void
    {
        $this->pagedCalls[] = $input;

        if ($this->runInline) {
            $this->runPaged($input->directoryImportId);
        }
    }

    private function runChunked(int $directoryImportId): void
    {
        $finalizeActivity = app(FinalizeDirectoryImportActivity::class);

        $plan = app(PrepareDirectoryImportActivity::class)->prepare($directoryImportId);
        $totalDataRows = max(0, $plan['totalRows'] - $plan['firstDataRow'] + 1);
        $totalChunks = $totalDataRows > 0 ? (int)ceil($totalDataRows / $plan['chunkSize']) : 0;
        $chunkActivity = app(ImportDirectoryExcelChunkActivity::class);

        for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
            $startRow = $plan['firstDataRow'] + $chunkIndex * $plan['chunkSize'];

            try {
                $chunkActivity->importChunk($directoryImportId, $startRow, $plan['chunkSize']);
            } catch (Throwable $exception) {
                $finalizeActivity->fail($directoryImportId, $exception->getMessage());

                return;
            }
        }

        $finalizeActivity->complete($directoryImportId);
    }

    private function runPaged(int $directoryImportId): void
    {
        $finalizeActivity = app(FinalizeDirectoryImportActivity::class);
        $pageActivity = app(FetchDirectoryImportPageActivity::class);

        for ($page = 1;; $page++) {
            try {
                $result = $pageActivity->fetchPage($directoryImportId, $page);
            } catch (Throwable $exception) {
                $finalizeActivity->fail($directoryImportId, $exception->getMessage());

                return;
            }

            if (!$result['hasMore']) {
                break;
            }
        }

        $finalizeActivity->complete($directoryImportId);
    }
}
