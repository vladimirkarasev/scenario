<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Module\Actions\Enums\ActionType;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\Directory;

/**
 * Связывает cron-расписание синхронизации справочника с модулем Actions:
 * под каждый справочник заводится служебный Action (тип «Импорт справочника»,
 * proxy-источник) и его ActionSchedule с cron. Запуском занимается уже готовая
 * команда actions:run-scheduled → ActionScheduleService::runDue.
 */
final class DirectorySyncScheduleService
{
    public function __construct(
        private readonly ActionScheduleService $schedules,
    ) {
    }

    public function findSchedule(Directory $directory): ?ActionSchedule
    {
        return $this->findAction($directory)?->schedule()->first();
    }

    public function upsert(Directory $directory, bool $enabled, ?string $cron, ?string $timezone): ActionSchedule
    {
        $code = $this->code($directory);

        $action = Action::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => 'Синхронизация справочника: '.$directory->name,
                'key' => $code,
                'type' => ActionType::DirectoryImport->value,
                'is_active' => true,
                'config' => [
                    'directory_id' => $directory->id,
                    'source_type' => DirectoryImportSourceType::Proxy->value,
                ],
            ],
        );

        $schedule = $this->schedules->upsert(
            action: $action,
            enabled: $enabled,
            cron: $cron,
            timezone: $timezone,
            input: [],
            options: [],
            settings: [],
        );

        // Когда активен cron — переводим справочник в cron-режим, чтобы интервальный
        // queueDue его не дублировал.
        $this->setScheduleMode($directory, $enabled && $cron !== null && $cron !== '' ? 'cron' : 'interval');

        return $schedule;
    }

    public function disable(Directory $directory): void
    {
        $schedule = $this->findSchedule($directory);

        if ($schedule instanceof ActionSchedule) {
            $this->schedules->delete($schedule);
        }

        $this->setScheduleMode($directory, 'interval');
    }

    private function findAction(Directory $directory): ?Action
    {
        return Action::query()->where('code', $this->code($directory))->first();
    }

    private function setScheduleMode(Directory $directory, string $mode): void
    {
        $config = $directory->api_config_json ?? [];
        $config['schedule_mode'] = $mode;

        $directory->forceFill([
            'api_config_json' => $config,
            // В cron-режиме обнуляем интервальный next_sync_at.
            'next_sync_at' => $mode === 'cron' ? null : $directory->next_sync_at,
        ])->save();
    }

    private function code(Directory $directory): string
    {
        return 'directory_sync_'.$directory->id;
    }
}
