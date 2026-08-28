<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Module\Actions\DTO\RunActionsData;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;
use Module\Actions\Repositories\ActionScheduleRepository;
use Module\Actions\Temporal\ActionScheduleSyncerInterface;

final readonly class ActionScheduleService
{
    /** @var array<string, string> Удобные пресеты для UI: код пресета → cron-выражение. */
    public const array PRESETS = [
        'every_minute' => '* * * * *',
        'hourly' => '0 * * * *',
        'daily' => '0 9 * * *',
        'weekly' => '0 9 * * 1',
        'monthly' => '0 9 1 * *',
    ];

    public function __construct(
        private ActionScheduleRepository $schedules,
        private ActionScheduleSyncerInterface $syncer,
    ) {}

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $options
     * @param array<string, mixed> $settings
     */
    public function upsert(
        Action $action,
        bool $enabled,
        ?string $cron,
        ?string $timezone,
        array $input,
        array $options,
        array $settings,
    ): ActionSchedule {
        return DB::transaction(function () use ($action, $enabled, $cron, $timezone, $input, $options, $settings): ActionSchedule {
            $schedule = $this->schedules->upsertForAction($action->id, [
                'enabled' => $enabled,
                'cron' => $cron,
                'timezone' => $timezone ?: config('app.timezone', 'UTC'),
                'input' => $this->stringKeyed($input),
                'options' => $this->stringKeyed($options),
                'settings' => $this->stringKeyed($settings),
            ]);

            $this->schedules->update($schedule, [
                'next_run_at' => $enabled ? $this->nextRunAt($schedule) : null,
            ]);

            $schedule = $schedule->fresh(['action']) ?? $schedule;

            $this->syncer->sync($schedule);

            return $schedule;
        });
    }

    public function delete(ActionSchedule $schedule): void
    {
        $scheduleId = $schedule->id;
        $this->schedules->delete($schedule);
        $this->syncer->delete($scheduleId);
    }

    /** @return list<array<string, mixed>> */
    public function listPayload(): array
    {
        return array_values($this->schedules->orderedWithAction()
            ->map(static fn (ActionSchedule $schedule): array => [
                'id' => $schedule->id,
                'enabled' => $schedule->enabled,
                'cron' => $schedule->cron,
                'timezone' => $schedule->timezone,
                'last_run_at' => $schedule->last_run_at?->toIso8601String(),
                'next_run_at' => $schedule->next_run_at?->toIso8601String(),
                'action' => $schedule->action ? [
                    'id' => $schedule->action->id,
                    'name' => $schedule->action->name,
                    'code' => $schedule->action->code,
                    'type' => $schedule->action->type,
                    'is_active' => $schedule->action->is_active,
                ] : null,
            ])
            ->all());
    }

    /** @return array<string, mixed>|null */
    public function payload(?ActionSchedule $schedule): ?array
    {
        if ($schedule === null) {
            return null;
        }

        return [
            'id' => $schedule->id,
            'action_id' => $schedule->action_id,
            'enabled' => $schedule->enabled,
            'cron' => $schedule->cron,
            'timezone' => $schedule->timezone,
            'input' => $schedule->input,
            'options' => $schedule->options,
            'settings' => $schedule->settings,
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
        ];
    }

    public function nextRunAt(ActionSchedule $schedule, ?Carbon $from = null): ?Carbon
    {
        if ($schedule->cron === null || $schedule->cron === '' || ! CronExpression::isValidExpression($schedule->cron)) {
            return null;
        }

        $timezoneRaw = config('app.timezone', 'UTC');
        $scheduleTimezone = $schedule->timezone;
        $timezone = $scheduleTimezone !== '' ? $scheduleTimezone : (is_string($timezoneRaw) ? $timezoneRaw : 'UTC');
        $current = ($from ?? now())->copy()->timezone($timezone);

        $next = (new CronExpression($schedule->cron))->getNextRunDate($current->toDateTimeImmutable());

        return Carbon::instance($next)->utc();
    }

    public static function isValidCron(string $cron): bool
    {
        return CronExpression::isValidExpression($cron);
    }

    public function runDataForSchedule(ActionSchedule $schedule, Action $action): RunActionsData
    {
        $options = is_array($schedule->options) ? $schedule->options : [];

        return new RunActionsData(
            mode: is_string($options['mode'] ?? null) ? (string) $options['mode'] : 'sequential',
            actions: $this->codeMap($options['actions'] ?? null, [$action->code => $action->id]),
            before: $this->codeMap($options['before'] ?? null),
            after: $this->codeMap($options['after'] ?? null),
            onError: $this->codeMap($options['on_error'] ?? null),
            input: is_array($schedule->input) ? $this->stringKeyed($schedule->input) : [],
            schedule: null,
            canManageActions: true,
        );
    }

    /**
     * @param  array<string, string> $fallback
     * @return array<string, string>
     */
    private function codeMap(mixed $value, array $fallback = []): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        $result = [];

        foreach ($value as $code => $id) {
            if (is_string($code) && $code !== '' && is_scalar($id)) {
                $result[$code] = (string) $id;
            }
        }

        return $result !== [] ? $result : $fallback;
    }

    /**
     * @param  array<mixed, mixed>  $items
     * @return array<string, mixed>
     */
    private function stringKeyed(array $items): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
