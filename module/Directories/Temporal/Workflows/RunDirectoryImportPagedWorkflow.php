<?php

declare(strict_types=1);

namespace Module\Directories\Temporal\Workflows;

use Module\Directories\Temporal\Activities\FetchDirectoryImportPageActivityInterface;
use Module\Directories\Temporal\Activities\FinalizeDirectoryImportActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Workflow;

final class RunDirectoryImportPagedWorkflow implements RunDirectoryImportPagedWorkflowInterface
{
    /**
     * @return \Generator<int, mixed, mixed, void>
     */
    public function run(int $directoryImportId)
    {
        $pageActivity = Workflow::newActivityStub(
            FetchDirectoryImportPageActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(120)
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1)),
        );

        $finalizeActivity = Workflow::newActivityStub(
            FinalizeDirectoryImportActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(30),
        );

        for ($page = 1;; $page++) {
            try {
                /** @var array{hasMore: bool} $result */
                $result = yield $pageActivity->fetchPage($directoryImportId, $page);
            } catch (ActivityFailure $exception) {
                $previous = $exception->getPrevious();
                $detail = $previous instanceof ApplicationFailure ? $previous->getDetails()->getValue(0, 'string') : null;
                $message = is_string($detail) ? $detail : $exception->getMessage();

                yield $finalizeActivity->fail($directoryImportId, $message);

                return;
            }

            if (!$result['hasMore']) {
                break;
            }
        }

        yield $finalizeActivity->complete($directoryImportId);
    }
}
