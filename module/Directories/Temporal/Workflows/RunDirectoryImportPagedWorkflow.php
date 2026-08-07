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

        Workflow::setCurrentDetails("### Импорт справочника\nСтраница 1…");

        $endpoint = null;
        $totalAdded = 0;
        $totalUpdated = 0;
        $totalFailed = 0;

        for ($page = 1;; $page++) {
            try {
                /** @var array{hasMore: bool, endpoint?: ?string, requestId?: ?string, received?: int, added?: int, updated?: int, failed?: int} $result */
                $result = yield $pageActivity->fetchPage($directoryImportId, $page);
            } catch (ActivityFailure $exception) {
                $previous = $exception->getPrevious();
                $detail = $previous instanceof ApplicationFailure ? $previous->getDetails()->getValue(0, 'string') : null;
                $message = is_string($detail) ? $detail : $exception->getMessage();

                Workflow::setCurrentDetails($this->failedDetails($endpoint, $message));

                yield $finalizeActivity->fail($directoryImportId, $message);

                return;
            }

            $endpoint ??= $result['endpoint'] ?? null;
            $totalAdded += $result['added'] ?? 0;
            $totalUpdated += $result['updated'] ?? 0;
            $totalFailed += $result['failed'] ?? 0;

            Workflow::setCurrentDetails($this->pageDetails(
                $endpoint,
                $result['requestId'] ?? null,
                $page,
                $result['received'] ?? 0,
                $totalAdded,
                $totalUpdated,
                $totalFailed,
            ));

            if (!$result['hasMore']) {
                break;
            }
        }

        /** @var array{deleted: int} $finalizeResult */
        $finalizeResult = yield $finalizeActivity->complete($directoryImportId);

        Workflow::setCurrentDetails($this->completedDetails(
            $endpoint,
            $totalAdded,
            $totalUpdated,
            $totalFailed,
            $finalizeResult['deleted'],
        ));
    }

    private function pageDetails(
        ?string $endpoint,
        ?string $requestId,
        int $page,
        int $received,
        int $totalAdded,
        int $totalUpdated,
        int $totalFailed,
    ): string {
        $lines = ['### Импорт справочника'];

        if ($endpoint !== null) {
            $lines[] = "**Proxy:** {$endpoint}".($requestId !== null ? " (request_id: `{$requestId}`)" : '');
        }

        $lines[] = "**Страница:** {$page}, получено: {$received}";
        $lines[] = "**Итого:** добавлено {$totalAdded}, обновлено {$totalUpdated}, ошибок {$totalFailed}";

        return implode("\n", $lines);
    }

    private function completedDetails(
        ?string $endpoint,
        int $totalAdded,
        int $totalUpdated,
        int $totalFailed,
        int $deleted,
    ): string {
        $lines = ['### Импорт справочника завершён'];

        if ($endpoint !== null) {
            $lines[] = "**Proxy:** {$endpoint}";
        }

        $lines[] = "**Итого:** добавлено {$totalAdded}, обновлено {$totalUpdated}, удалено {$deleted}, ошибок {$totalFailed}";

        return implode("\n", $lines);
    }

    private function failedDetails(?string $endpoint, string $message): string
    {
        $lines = ['### Импорт справочника провалился'];

        if ($endpoint !== null) {
            $lines[] = "**Proxy:** {$endpoint}";
        }

        $lines[] = "**Ошибка:** {$message}";

        return implode("\n", $lines);
    }
}
