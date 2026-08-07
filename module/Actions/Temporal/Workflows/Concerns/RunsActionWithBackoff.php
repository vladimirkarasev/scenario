<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Workflows\Concerns;

use Module\Actions\Temporal\Activities\ExecuteActionActivityInterface;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Workflow;

trait RunsActionWithBackoff
{
    /**
     * @param  ExecuteActionActivityInterface  $activity  прокси Workflow::newActivityStub(), не implements интерфейс в рантайме
     * @param  array<string, mixed>  $context
     * @param  list<int>  $backoff
     * @return \Generator<int, mixed, mixed, array{status: string, output: array<string, mixed>, error: string|null}>
     */
    private function runActionWithBackoff(
        object $activity,
        string $actionId,
        string $code,
        array $context,
        ?string $scenarioRunId,
        int $delayBefore,
        array $backoff,
        ?string $actionNodeId = null,
    ): \Generator {
        if ($delayBefore > 0) {
            yield Workflow::timer($delayBefore);
        }

        $attempts = $backoff !== [] ? $backoff : [0];
        $lastIndex = array_key_last($attempts);

        foreach ($attempts as $index => $delay) {
            if ($delay > 0) {
                yield Workflow::timer($delay);
            }

            Workflow::setCurrentDetails($this->runningDetails($code, $index + 1, count($attempts)));

            try {
                /** @var array{status: string, output: array<string, mixed>, error: string|null} $result */
                $result = yield $activity->execute($actionId, $context, $code, $scenarioRunId, $index + 1, $actionNodeId);

                Workflow::setCurrentDetails($this->resultDetails($code, $result));

                return $result;
            } catch (ActivityFailure $exception) {
                if ($index === $lastIndex) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('unreachable');
    }

    private function runningDetails(string $code, int $attempt, int $totalAttempts): string
    {
        $status = $totalAttempts > 1 ? "Попытка {$attempt}/{$totalAttempts}…" : 'Выполняется…';

        return <<<MARKDOWN
        ### Экшен: `{$code}`
        {$status}
        MARKDOWN;
    }

    /** @param array{status: string, output: array<string, mixed>, error: string|null} $result */
    private function resultDetails(string $code, array $result): string
    {
        $output = $result['output'];
        $request = is_array($output['request'] ?? null) ? $output['request'] : null;

        $sections = [
            "### Экшен: `{$code}`",
            "**Статус:** {$result['status']}",
            $this->requestLine($request),
            $this->responseLine($output, hasRequest: $request !== null),
            $this->errorLine($result['error']),
        ];

        return implode("\n", array_filter($sections, static fn (?string $line): bool => $line !== null));
    }

    /** @param array<mixed, mixed>|null $request */
    private function requestLine(?array $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $method = is_string($request['method'] ?? null) ? $request['method'] : '?';
        $targetRaw = $request['url'] ?? $request['endpoint_uuid'] ?? '?';
        $target = is_scalar($targetRaw) ? (string) $targetRaw : '?';

        return "**Отправлено:** {$method} {$target}";
    }

    /** @param array<string, mixed> $output */
    private function responseLine(array $output, bool $hasRequest): ?string
    {
        if (array_key_exists('status', $output) || array_key_exists('body', $output)) {
            $statusRaw = $output['status'] ?? '?';
            $status = is_scalar($statusRaw) ? (string) $statusRaw : '?';

            return "**Получено:** HTTP {$status} — ".$this->describeBody($output['body'] ?? null);
        }

        if (!$hasRequest && $output !== []) {
            return '**Результат:** '.$this->describeBody($output);
        }

        return null;
    }

    private function errorLine(?string $error): ?string
    {
        return $error !== null && $error !== '' ? "**Ошибка:** {$error}" : null;
    }

    private function describeBody(mixed $body): string
    {
        if (is_array($body)) {
            if (array_is_list($body)) {
                $count = count($body);

                return "{$count} ".($count === 1 ? 'запись' : 'записей');
            }

            return implode(', ', array_slice(array_map(strval(...), array_keys($body)), 0, 5));
        }

        return is_scalar($body) ? (string) $body : gettype($body);
    }
}
