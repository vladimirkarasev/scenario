<?php

declare(strict_types=1);

namespace Module\Schedule\Services;

use Cron\CronExpression;

final readonly class ScheduleDefinition
{
    /** @param  list<mixed>  $workflowInput */
    private function __construct(
        public string $scope,
        public string $subjectId,
        public bool $enabled,
        public ?CronExpression $cron,
        public string $timezone,
        public string $workflowType,
        public string $taskQueue,
        public array $workflowInput,
    ) {
    }

    /**
     * `$taskQueue` defaults to the Temporal SDK's own `'default'` constant — callers that
     * care about task queue isolation should override it via {@see self::withTaskQueue()}
     * using an injected {@see \Module\Schedule\Support\TemporalTaskQueue}, not read config here.
     */
    public static function new(string $scope, string $subjectId, string $workflowType): self
    {
        $timezoneRaw = config('app.timezone', 'UTC');

        return new self(
            scope: $scope,
            subjectId: $subjectId,
            enabled: true,
            cron: null,
            timezone: is_string($timezoneRaw) && $timezoneRaw !== '' ? $timezoneRaw : 'UTC',
            workflowType: $workflowType,
            taskQueue: 'default',
            workflowInput: [],
        );
    }

    public function withEnabled(bool $enabled): self
    {
        return new self(
            $this->scope,
            $this->subjectId,
            $enabled,
            $this->cron,
            $this->timezone,
            $this->workflowType,
            $this->taskQueue,
            $this->workflowInput,
        );
    }

    public function withCron(?CronExpression $cron): self
    {
        return new self(
            $this->scope,
            $this->subjectId,
            $this->enabled,
            $cron,
            $this->timezone,
            $this->workflowType,
            $this->taskQueue,
            $this->workflowInput,
        );
    }

    public function withCronString(?string $cron): self
    {
        if ($cron === null || $cron === '' || !CronExpression::isValidExpression($cron)) {
            return $this->withCron(null);
        }

        return $this->withCron(new CronExpression($cron));
    }

    public function everyMinute(): self
    {
        return $this->withCron(new CronExpression('* * * * *'));
    }

    public function hourly(): self
    {
        return $this->withCron(new CronExpression('0 * * * *'));
    }

    public function withTimezone(?string $timezone): self
    {
        return new self(
            $this->scope,
            $this->subjectId,
            $this->enabled,
            $this->cron,
            $timezone !== null && $timezone !== '' ? $timezone : $this->timezone,
            $this->workflowType,
            $this->taskQueue,
            $this->workflowInput,
        );
    }

    public function withTaskQueue(string $taskQueue): self
    {
        return new self(
            $this->scope,
            $this->subjectId,
            $this->enabled,
            $this->cron,
            $this->timezone,
            $this->workflowType,
            $taskQueue,
            $this->workflowInput,
        );
    }

    /** @param  list<mixed>  $workflowInput */
    public function withWorkflowInput(array $workflowInput): self
    {
        return new self(
            $this->scope,
            $this->subjectId,
            $this->enabled,
            $this->cron,
            $this->timezone,
            $this->workflowType,
            $this->taskQueue,
            $workflowInput,
        );
    }
}
