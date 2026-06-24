<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use App\Models\Category;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Module\Actions\Enums\ActionType;
use Module\Actions\Models\Action;
use Module\Actions\Models\ActionSchedule;
use Module\Categories\Repositories\CategoryRepositoryContract;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\Directory;

/**
 * Связывает cron-расписание синхронизации справочника с модулем Actions:
 * под каждый справочник заводится служебный Action (тип «Импорт справочника»,
 * proxy-источник) и его ActionSchedule с cron. Запуском занимается уже готовая
 * команда actions:run-scheduled → ActionScheduleService::runDue.
 *
 * Все служебные sync-экшены складываются в системный раздел «Синхронизация справочников»
 * ({@see self::SYNC_SECTION_NAME}, is_system) — пользователь не может его удалить.
 */
final readonly class DirectorySyncScheduleService
{
    private const string SYNC_SECTION_NAME = 'Синхронизация справочников';

    public function __construct(
        private ActionScheduleService $schedules,
        private CategoryRepositoryContract $categories,
    ) {}

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
                'slug' => $code,
                'type' => ActionType::DirectoryImport->value,
                'is_active' => true,
                'config' => [
                    'directory_id' => $directory->id,
                    'source_type' => DirectoryImportSourceType::Proxy->value,
                ],
            ],
        );

        $this->attachToSyncSection($action, $directory->project_id);

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
        // Код экшена обязан матчить регулярку ActionRequest `^[a-z][a-z0-9_]*$`,
        // поэтому дефисы UUID заменяем на подчёркивания (иначе редактирование падает 422).
        return 'directory_sync_'.str_replace('-', '_', $directory->id);
    }

    /** Привязать служебный sync-экшен к системному разделу «Синхронизация справочников». */
    private function attachToSyncSection(Action $action, ?string $projectId): void
    {
        $category = $this->resolveSyncCategory($projectId);

        $action->categories()->syncWithoutDetaching([
            $category->id => ['project_id' => $projectId],
        ]);
    }

    /**
     * Найти или создать системный раздел «Синхронизация справочников» для (Action, проект).
     * Создаётся через CachedCategoryRepository (сбрасывает кэш разделов).
     */
    private function resolveSyncCategory(?string $projectId): Category
    {
        $existing = Category::query()
            ->where('is_system', true)
            ->where('name', self::SYNC_SECTION_NAME)
            ->whereExists(function (QueryBuilder $q) use ($projectId): void {
                $q->from('model_has_categories')
                    ->whereColumn('model_has_categories.category_id', 'categories.id')
                    ->where('model_has_categories.model_type', Action::class);

                $projectId === null
                    ? $q->whereNull('model_has_categories.project_id')
                    : $q->where('model_has_categories.project_id', $projectId);
            })
            ->first();

        if ($existing instanceof Category) {
            return $existing;
        }

        $category = $this->categories->create([
            'name' => self::SYNC_SECTION_NAME,
            'parent_id' => null,
            'is_active' => true,
            'is_system' => true,
        ], null);

        DB::table('model_has_categories')->insertOrIgnore([
            'category_id' => $category->id,
            'model_id' => $category->id,
            'model_type' => Action::class,
            'project_id' => $projectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $category;
    }
}
