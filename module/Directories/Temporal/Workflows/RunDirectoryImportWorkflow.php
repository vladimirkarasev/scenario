<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Module\Directories\Temporal\Activities\FinalizeDirectoryImportActivityInterface;
use Module\Directories\Temporal\Activities\ImportDirectoryExcelChunkActivityInterface;
use Module\Directories\Temporal\Activities\PrepareDirectoryImportActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Workflow;

final class RunDirectoryImportWorkflow implements RunDirectoryImportWorkflowInterface
{
    /**
     * @return \Generator<int, mixed, mixed, void>
     */
    public function run(int $directoryImportId)
    {
        $prepareActivity = Workflow::newActivityStub(
            PrepareDirectoryImportActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(60)
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        /** @var array{headingRow: int, firstDataRow: int, chunkSize: int, totalRows: int, chunkTimeoutSeconds: int} $plan */
        $plan = yield $prepareActivity->prepare($directoryImportId);

        $totalDataRows = max(0, $plan['totalRows'] - $plan['firstDataRow'] + 1);
        $totalChunks = $totalDataRows > 0 ? (int)ceil($totalDataRows / $plan['chunkSize']) : 0;

        $chunkActivity = Workflow::newActivityStub(
            ImportDirectoryExcelChunkActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout($plan['chunkTimeoutSeconds'])
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        $finalizeActivity = Workflow::newActivityStub(
            FinalizeDirectoryImportActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
            $startRow = $plan['firstDataRow'] + $chunkIndex * $plan['chunkSize'];

            try {
                yield $chunkActivity->importChunk($directoryImportId, $startRow, $plan['chunkSize']);
            } catch (ActivityFailure $exception) {
                $previous = $exception->getPrevious();
                $detail = $previous instanceof ApplicationFailure ? $previous->getDetails()->getValue(0, 'string') : null;
                $message = is_string($detail) ? $detail : $exception->getMessage();

                yield $finalizeActivity->fail($directoryImportId, $message);

                return;
            }
        }

        yield $finalizeActivity->complete($directoryImportId);
    }
}
