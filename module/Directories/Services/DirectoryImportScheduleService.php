<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImportSchedule;
use Module\Directories\Repositories\DirectoryImportScheduleRepository;

final class DirectoryImportScheduleService
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly DirectoryImportScheduleRepository $schedules,
    ) {}

    /**
     * @param array<string, string>            $mapping
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed>             $remote
     */
    public function upsert(
        Directory $directory,
        bool $enabled,
        string $frequency,
        string $runAt,
        ?string $timezone,
        string $mode,
        array $mapping,
        array $fields,
        array $remote,
        ?string $matchBy,
        int $chunkSize,
    ): DirectoryImportSchedule {
        $schedule = $this->schedules->upsertForDirectory($directory->id, [
            'enabled' => $enabled,
            'frequency' => $frequency,
            'run_at' => $runAt,
            'timezone' => $timezone ?: config('app.timezone', 'UTC'),
            'mode' => $mode,
            'match_by' => $matchBy,
            'chunk_size' => $chunkSize,
            'mapping_json' => $this->normalizeMapping($mapping),
            'fields_json' => $this->normalizeFields($fields),
            'remote_config_json' => $this->normalizeRemoteConfig($remote, $chunkSize),
        ]);

        $this->schedules->update($schedule, [
            'next_run_at' => $enabled ? $this->nextRunAt($schedule) : null,
        ]);

        return $schedule->fresh() ?? $schedule;
    }

    public function runDue(): int
    {
        $ran = 0;

        $this->schedules
            ->due()
            ->each(function (DirectoryImportSchedule $schedule) use (&$ran): void {
                DB::transaction(function () use ($schedule, &$ran): void {
                    $locked = $this->schedules->lock($schedule->id);

                    if (
                        $locked === null
                        || ! $locked->enabled
                        || $locked->next_run_at === null
                        || $locked->next_run_at->isFuture()
                    ) {
                        return;
                    }

                    /** @var array<string, string> $mapping */
                    $mapping = is_array($locked->mapping_json) ? $locked->mapping_json : [];
                    /** @var array<int, array<string, mixed>> $fields */
                    $fields = is_array($locked->fields_json) ? $locked->fields_json : [];
                    /** @var array<string, mixed> $remote */
                    $remote = is_array($locked->remote_config_json) ? $locked->remote_config_json : [];
                    $mode = $locked->mode ?? DirectoryImportMode::Replace->value;
                    $chunkSize = $locked->chunk_size ?? 500;
                    $matchBy = is_string($locked->match_by) ? $locked->match_by : null;
                    $options = DirectoryImportOptions::forMode($mode);

                    $this->importService->queue(new DirectoryImportData(
                        directory: $locked->directory()->firstOrFail(),
                        file: null,
                        mode: DirectoryImportMode::from($mode),
                        sourceType: DirectoryImportSourceType::Remote,
                        mapping: $mapping,
                        fields: $fields,
                        remote: $remote,
                        matchBy: $matchBy,
                        parentKeyField: null,
                        chunkSize: $chunkSize,
                        activate: true,
                        options: $options,
                        uploadedBy: null,
                        versionId: null,
                    ));

                    $now = now();

                    $this->schedules->update($locked, [
                        'last_run_at' => $now,
                        'next_run_at' => $this->nextRunAt($locked, $now),
                    ]);

                    $ran++;
                });
            });

        return $ran;
    }

    /** @return array<string, mixed>|null */
    public function payload(?DirectoryImportSchedule $schedule): ?array
    {
        if ($schedule === null) {
            return null;
        }

        return [
            'id' => $schedule->id,
            'enabled' => $schedule->enabled,
            'frequency' => $schedule->frequency,
            'run_at' => $schedule->run_at,
            'timezone' => $schedule->timezone,
            'mode' => $schedule->mode,
            'match_by' => $schedule->match_by,
            'chunk_size' => $schedule->chunk_size,
            'mapping_json' => $schedule->mapping_json,
            'fields_json' => $schedule->fields_json,
            'remote_config_json' => $schedule->remote_config_json,
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
        ];
    }

    private function nextRunAt(DirectoryImportSchedule $schedule, ?Carbon $from = null): Carbon
    {
        $timezoneRaw = config('app.timezone', 'UTC');
        $scheduleTimezone = $schedule->timezone;
        $timezone = ($scheduleTimezone !== '') ? $scheduleTimezone : (is_string($timezoneRaw) ? $timezoneRaw : 'UTC');
        $current = ($from ?? now())->copy()->timezone($timezone);
        $runAt = is_string($schedule->run_at) ? $schedule->run_at : '00:00';
        [$hours, $minutes] = array_pad(explode(':', $runAt), 2, '0');

        $next = $current->copy()->setTime((int) $hours, (int) $minutes, 0);

        if ($next->lessThanOrEqualTo($current)) {
            $next->addDay();
        }

        return $next->utc();
    }

    /**
     * @param  array<string, string> $mapping
     * @return array<string, string>
     */
    private function normalizeMapping(array $mapping): array
    {
        $result = [];

        foreach ($mapping as $column => $fieldKey) {
            $formatted = HeadingRowFormatter::format([$column])[0] ?? null;
            $key = is_string($formatted) ? $formatted : $column;
            $result[$key] = $fieldKey;
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFields(array $fields): array
    {
        return collect($fields)
            ->map(static fn (array $field): array => [
                'key' => is_string($field['key'] ?? null) ? $field['key'] : '',
                'name' => is_string($field['name'] ?? null) ? $field['name'] : '',
                'rules' => is_array($field['rules'] ?? null) ? $field['rules'] : ['nullable', 'string'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed> $remote
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed> $remote
     * @return array<string, mixed>
     */
    private function normalizeRemoteConfig(array $remote, int $chunkSize): array
    {
        /** @var array<string, mixed> $headers */
        $headers = collect(is_array($remote['headers'] ?? null) ? $remote['headers'] : [])
            ->mapWithKeys(static fn (mixed $value, mixed $key): array => [(string) $key => is_scalar($value) ? (string) $value : ''])
            ->all();
        /** @var array<string, mixed> $query */
        $query = collect(is_array($remote['query'] ?? null) ? $remote['query'] : [])
            ->mapWithKeys(static fn (mixed $value, mixed $key): array => [(string) $key => is_scalar($value) ? (string) $value : $value])
            ->all();

        return [
            'url' => trim(is_string($remote['url'] ?? null) ? $remote['url'] : ''),
            'items_path' => trim(is_string($remote['items_path'] ?? null) ? $remote['items_path'] : 'data'),
            'page_param' => trim(is_string($remote['page_param'] ?? null) ? $remote['page_param'] : 'page'),
            'per_page_param' => trim(is_string($remote['per_page_param'] ?? null) ? $remote['per_page_param'] : 'per_page'),
            'per_page' => max(1, is_int($remote['per_page'] ?? null) ? $remote['per_page'] : $chunkSize),
            'per_page_path' => trim(is_string($remote['per_page_path'] ?? null) ? $remote['per_page_path'] : ''),
            'start_page' => max(1, is_int($remote['start_page'] ?? null) ? $remote['start_page'] : 1),
            'headers' => $headers,
            'query' => $query,
        ];
    }
}
