<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Projects\Models\Project;

final class DemoDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->firstOrFail();

        // ── Категории ─────────────────────────────────────────────────────────

        $catHr = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000001'],
            ['name' => 'HR', 'parent_id' => null, 'is_active' => true],
        );

        $catOrg = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000002'],
            ['name' => 'Организационная структура', 'parent_id' => $catHr->id, 'is_active' => true],
        );

        $catIt = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000010'],
            ['name' => 'IT', 'parent_id' => null, 'is_active' => true],
        );

        $catInfra = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000011'],
            ['name' => 'Инфраструктура', 'parent_id' => $catIt->id, 'is_active' => true],
        );

        $catDev = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000012'],
            ['name' => 'Разработка', 'parent_id' => $catIt->id, 'is_active' => true],
        );

        $catOps = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000020'],
            ['name' => 'Операции', 'parent_id' => null, 'is_active' => true],
        );

        // ── 1. Грейды ─────────────────────────────────────────────────────────

        $grades = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000001',
            'name' => 'Грейды',
            'slug' => 'grades',
            'description' => 'Уровни грейдов сотрудников компании',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $grades->categories()->sync([$catHr->id]);

        $gradesSchema = [
            ['key' => 'name',       'name' => 'Название',       'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'level',      'name' => 'Уровень',        'type' => 'integer', 'nullable' => false, 'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'salary_min', 'name' => 'Оклад от (₽)',   'type' => 'integer', 'nullable' => true,  'default' => null, 'sort_order' => 2, 'rules' => []],
            ['key' => 'salary_max', 'name' => 'Оклад до (₽)',   'type' => 'integer', 'nullable' => true,  'default' => null, 'sort_order' => 3, 'rules' => []],
        ];

        $gradesVersion = $this->upsertVersion($grades->id, $gradesSchema);

        $this->upsertItems($gradesVersion->id, [
            ['key' => 'grade-1', 'data' => ['name' => 'Junior',    'level' => 1, 'salary_min' => 80000,  'salary_max' => 120000]],
            ['key' => 'grade-2', 'data' => ['name' => 'Middle',    'level' => 2, 'salary_min' => 130000, 'salary_max' => 200000]],
            ['key' => 'grade-3', 'data' => ['name' => 'Senior',    'level' => 3, 'salary_min' => 210000, 'salary_max' => 320000]],
            ['key' => 'grade-4', 'data' => ['name' => 'Lead',      'level' => 4, 'salary_min' => 330000, 'salary_max' => 450000]],
            ['key' => 'grade-5', 'data' => ['name' => 'Principal', 'level' => 5, 'salary_min' => 460000, 'salary_max' => null]],
        ]);

        // ── 2. Должности ──────────────────────────────────────────────────────

        $positions = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000002',
            'name' => 'Должности',
            'slug' => 'positions',
            'description' => 'Штатные должности сотрудников',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $positions->categories()->sync([$catOrg->id]);

        $positionsSchema = [
            ['key' => 'name',       'name' => 'Должность',       'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'department', 'name' => 'Подразделение',   'type' => 'string',  'nullable' => true,  'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'grade_min',  'name' => 'Грейд от',        'type' => 'integer', 'nullable' => true,  'default' => null, 'sort_order' => 2, 'rules' => []],
            ['key' => 'grade_max',  'name' => 'Грейд до',        'type' => 'integer', 'nullable' => true,  'default' => null, 'sort_order' => 3, 'rules' => []],
        ];

        $positionsVersion = $this->upsertVersion($positions->id, $positionsSchema);

        $this->upsertItems($positionsVersion->id, [
            ['key' => 'pos-dev-junior',  'data' => ['name' => 'Разработчик',            'department' => 'Разработка', 'grade_min' => 1, 'grade_max' => 2]],
            ['key' => 'pos-dev-senior',  'data' => ['name' => 'Старший разработчик',    'department' => 'Разработка', 'grade_min' => 3, 'grade_max' => 4]],
            ['key' => 'pos-teamlead',    'data' => ['name' => 'Тимлид',                 'department' => 'Разработка', 'grade_min' => 4, 'grade_max' => 5]],
            ['key' => 'pos-pm',          'data' => ['name' => 'Менеджер проекта',       'department' => 'Управление', 'grade_min' => 3, 'grade_max' => 5]],
            ['key' => 'pos-hr',          'data' => ['name' => 'HR-менеджер',            'department' => 'HR',         'grade_min' => 2, 'grade_max' => 4]],
            ['key' => 'pos-analyst',     'data' => ['name' => 'Аналитик',               'department' => 'Аналитика',  'grade_min' => 2, 'grade_max' => 3]],
        ]);

        // ── 3. Подразделения ──────────────────────────────────────────────────

        $departments = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000003',
            'name' => 'Подразделения',
            'slug' => 'departments',
            'description' => 'Организационная структура компании',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $departments->categories()->sync([$catOrg->id]);

        $departmentsSchema = [
            ['key' => 'name',        'name' => 'Название',          'type' => 'string', 'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'code',        'name' => 'Код',               'type' => 'string', 'nullable' => false, 'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'parent_name', 'name' => 'Родительский отдел', 'type' => 'string', 'nullable' => true,  'default' => null, 'sort_order' => 2, 'rules' => []],
        ];

        $departmentsVersion = $this->upsertVersion($departments->id, $departmentsSchema);

        $this->upsertItems($departmentsVersion->id, [
            ['key' => 'dept-dev',     'data' => ['name' => 'Разработка',   'code' => 'DEV', 'parent_name' => null]],
            ['key' => 'dept-hr',      'data' => ['name' => 'HR',           'code' => 'HR',  'parent_name' => null]],
            ['key' => 'dept-finance', 'data' => ['name' => 'Финансы',      'code' => 'FIN', 'parent_name' => null]],
            ['key' => 'dept-sales',   'data' => ['name' => 'Продажи',      'code' => 'SLS', 'parent_name' => null]],
            ['key' => 'dept-backend', 'data' => ['name' => 'Backend',      'code' => 'BE',  'parent_name' => 'Разработка']],
            ['key' => 'dept-frontend', 'data' => ['name' => 'Frontend',     'code' => 'FE',  'parent_name' => 'Разработка']],
        ]);

        // ── 4. Технологии ─────────────────────────────────────────────────────

        $technologies = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000004',
            'name' => 'Технологии',
            'slug' => 'technologies',
            'description' => 'Технологии и языки программирования',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $technologies->categories()->sync([$catDev->id]);

        $technologiesSchema = [
            ['key' => 'name',     'name' => 'Название',   'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'type',     'name' => 'Тип',        'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'level',    'name' => 'Уровень',    'type' => 'string',  'nullable' => true,  'default' => null, 'sort_order' => 2, 'rules' => []],
        ];

        $technologiesVersion = $this->upsertVersion($technologies->id, $technologiesSchema);

        $this->upsertItems($technologiesVersion->id, [
            ['key' => 'tech-php',        'data' => ['name' => 'PHP',        'type' => 'Backend',  'level' => 'Senior']],
            ['key' => 'tech-typescript', 'data' => ['name' => 'TypeScript', 'type' => 'Frontend', 'level' => 'Middle']],
            ['key' => 'tech-vue',        'data' => ['name' => 'Vue.js',     'type' => 'Frontend', 'level' => 'Middle']],
            ['key' => 'tech-react',      'data' => ['name' => 'React',      'type' => 'Frontend', 'level' => 'Middle']],
            ['key' => 'tech-laravel',    'data' => ['name' => 'Laravel',    'type' => 'Backend',  'level' => 'Senior']],
            ['key' => 'tech-docker',     'data' => ['name' => 'Docker',     'type' => 'DevOps',   'level' => 'Middle']],
            ['key' => 'tech-postgres',   'data' => ['name' => 'PostgreSQL', 'type' => 'Database', 'level' => 'Middle']],
            ['key' => 'tech-redis',      'data' => ['name' => 'Redis',      'type' => 'Database', 'level' => 'Junior']],
        ]);

        // ── 5. Серверы ────────────────────────────────────────────────────────

        $servers = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000005',
            'name' => 'Серверы',
            'slug' => 'servers',
            'description' => 'Реестр серверной инфраструктуры',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $servers->categories()->sync([$catInfra->id]);

        $serversSchema = [
            ['key' => 'name',        'name' => 'Имя хоста',   'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'environment', 'name' => 'Окружение',   'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'ip',          'name' => 'IP-адрес',    'type' => 'string',  'nullable' => true,  'default' => null, 'sort_order' => 2, 'rules' => []],
            ['key' => 'cpu',         'name' => 'CPU (cores)', 'type' => 'integer', 'nullable' => true,  'default' => null, 'sort_order' => 3, 'rules' => []],
            ['key' => 'ram_gb',      'name' => 'RAM (GB)',    'type' => 'integer', 'nullable' => true,  'default' => null, 'sort_order' => 4, 'rules' => []],
        ];

        $serversVersion = $this->upsertVersion($servers->id, $serversSchema);

        $this->upsertItems($serversVersion->id, [
            ['key' => 'srv-prod-web',  'data' => ['name' => 'prod-web-01',  'environment' => 'production',  'ip' => '10.0.1.10', 'cpu' => 8,  'ram_gb' => 32]],
            ['key' => 'srv-prod-db',   'data' => ['name' => 'prod-db-01',   'environment' => 'production',  'ip' => '10.0.1.20', 'cpu' => 16, 'ram_gb' => 64]],
            ['key' => 'srv-stage-web', 'data' => ['name' => 'stage-web-01', 'environment' => 'staging',     'ip' => '10.0.2.10', 'cpu' => 4,  'ram_gb' => 16]],
            ['key' => 'srv-dev-01',    'data' => ['name' => 'dev-shared-01', 'environment' => 'development',  'ip' => '10.0.3.10', 'cpu' => 4,  'ram_gb' => 8]],
        ]);

        // ── 6. Статусы задач ──────────────────────────────────────────────────

        $taskStatuses = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000006',
            'name' => 'Статусы задач',
            'slug' => 'task-statuses',
            'description' => 'Жизненный цикл задачи в трекере',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $taskStatuses->categories()->sync([$catOps->id]);

        $taskStatusesSchema = [
            ['key' => 'name',     'name' => 'Название',    'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'color',    'name' => 'Цвет',        'type' => 'string',  'nullable' => true,  'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'is_final', 'name' => 'Финальный',   'type' => 'boolean', 'nullable' => false, 'default' => null, 'sort_order' => 2, 'rules' => []],
            ['key' => 'order',    'name' => 'Порядок',     'type' => 'integer', 'nullable' => false, 'default' => null, 'sort_order' => 3, 'rules' => []],
        ];

        $taskStatusesVersion = $this->upsertVersion($taskStatuses->id, $taskStatusesSchema);

        $this->upsertItems($taskStatusesVersion->id, [
            ['key' => 'ts-new',         'data' => ['name' => 'Новая',        'color' => '#94a3b8', 'is_final' => false, 'order' => 1]],
            ['key' => 'ts-in-progress', 'data' => ['name' => 'В работе',     'color' => '#3b82f6', 'is_final' => false, 'order' => 2]],
            ['key' => 'ts-review',      'data' => ['name' => 'На проверке',  'color' => '#f59e0b', 'is_final' => false, 'order' => 3]],
            ['key' => 'ts-done',        'data' => ['name' => 'Выполнена',    'color' => '#10b981', 'is_final' => true,  'order' => 4]],
            ['key' => 'ts-cancelled',   'data' => ['name' => 'Отменена',     'color' => '#ef4444', 'is_final' => true,  'order' => 5]],
        ]);

        // ── 7. Приоритеты ─────────────────────────────────────────────────────

        $priorities = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000007',
            'name' => 'Приоритеты',
            'slug' => 'priorities',
            'description' => 'Приоритеты задач и инцидентов',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $priorities->categories()->sync([$catOps->id]);

        $prioritiesSchema = [
            ['key' => 'name',  'name' => 'Название', 'type' => 'string',  'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'level', 'name' => 'Уровень',  'type' => 'integer', 'nullable' => false, 'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'sla_hours', 'name' => 'SLA (ч)', 'type' => 'integer', 'nullable' => true, 'default' => null, 'sort_order' => 2, 'rules' => []],
        ];

        $prioritiesVersion = $this->upsertVersion($priorities->id, $prioritiesSchema);

        $this->upsertItems($prioritiesVersion->id, [
            ['key' => 'prio-critical', 'data' => ['name' => 'Критический', 'level' => 1, 'sla_hours' => 2]],
            ['key' => 'prio-high',     'data' => ['name' => 'Высокий',     'level' => 2, 'sla_hours' => 8]],
            ['key' => 'prio-medium',   'data' => ['name' => 'Средний',     'level' => 3, 'sla_hours' => 24]],
            ['key' => 'prio-low',      'data' => ['name' => 'Низкий',      'level' => 4, 'sla_hours' => 72]],
        ]);

        // ── 8. Офисы ──────────────────────────────────────────────────────────

        $offices = $this->upsertDirectory($project->id, [
            'id' => '019f0000-0000-7000-b000-000000000008',
            'name' => 'Офисы',
            'slug' => 'offices',
            'description' => 'Адреса офисов компании',
            'source_type' => 'manual',
            'sync_status' => 'idle',
        ]);
        $offices->categories()->sync([$catOrg->id]);

        $officesSchema = [
            ['key' => 'city',    'name' => 'Город',   'type' => 'string', 'nullable' => false, 'default' => null, 'sort_order' => 0, 'rules' => []],
            ['key' => 'country', 'name' => 'Страна',  'type' => 'string', 'nullable' => false, 'default' => null, 'sort_order' => 1, 'rules' => []],
            ['key' => 'address', 'name' => 'Адрес',   'type' => 'string', 'nullable' => true,  'default' => null, 'sort_order' => 2, 'rules' => []],
            ['key' => 'capacity', 'name' => 'Мест',    'type' => 'integer', 'nullable' => true,  'default' => null, 'sort_order' => 3, 'rules' => []],
        ];

        $officesVersion = $this->upsertVersion($offices->id, $officesSchema);

        $this->upsertItems($officesVersion->id, [
            ['key' => 'office-msk',  'data' => ['city' => 'Москва',          'country' => 'Россия', 'address' => 'ул. Льва Толстого, 16',  'capacity' => 250]],
            ['key' => 'office-spb',  'data' => ['city' => 'Санкт-Петербург', 'country' => 'Россия', 'address' => 'Невский пр., 100',        'capacity' => 80]],
            ['key' => 'office-nsk',  'data' => ['city' => 'Новосибирск',     'country' => 'Россия', 'address' => 'ул. Ленина, 52',          'capacity' => 40]],
            ['key' => 'office-kzn',  'data' => ['city' => 'Казань',          'country' => 'Россия', 'address' => 'ул. Баумана, 9',          'capacity' => 30]],
        ]);

        DirectoryCache::forgetList($project->id);
    }

    /** @param array<string, mixed> $attributes */
    private function upsertDirectory(string $projectId, array $attributes): Directory
    {
        /** @var Directory $dir */
        $dir = Directory::query()->updateOrCreate(
            ['id' => $attributes['id']],
            array_merge($attributes, ['project_id' => $projectId]),
        );

        return $dir;
    }

    /** @param array<int, array<string, mixed>> $schema */
    private function upsertVersion(string|int $directoryId, array $schema): DirectoryVersion
    {
        /** @var DirectoryVersion $version */
        $version = DirectoryVersion::query()->updateOrCreate(
            ['directory_id' => $directoryId, 'version_number' => 1],
            [
                'status' => 'ready',
                'is_active' => true,
                'schema_json' => $schema,
            ],
        );

        return $version;
    }

    /**
     * @param array<int, array{key: string, data: array<string, mixed>}> $items
     */
    private function upsertItems(int $versionId, array $items): void
    {
        foreach ($items as $item) {
            $search = implode(' ', array_filter(array_values($item['data']), 'is_string'));

            DirectoryItem::query()->updateOrCreate(
                ['directory_version_id' => $versionId, 'external_key' => $item['key']],
                ['data_json' => $item['data'], 'search_text' => $search],
            );
        }
    }
}
