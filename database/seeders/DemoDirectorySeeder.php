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

        $catFinance = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000030'],
            ['name' => 'Финансы', 'parent_id' => null, 'is_active' => true],
        );

        $catSales = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000040'],
            ['name' => 'Продажи', 'parent_id' => null, 'is_active' => true],
        );

        $catLogistics = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000050'],
            ['name' => 'Логистика', 'parent_id' => null, 'is_active' => true],
        );

        $catReference = Category::query()->updateOrCreate(
            ['id' => '019f0000-0000-7000-a000-000000000060'],
            ['name' => 'Общие справочники', 'parent_id' => null, 'is_active' => true],
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
        $this->syncCategory($grades, $catHr->id, $project->id);

        $gradesSchema = [
            [
                'key' => 'name',
                'name' => 'Название',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'level',
                'name' => 'Уровень',
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'salary_min',
                'name' => 'Оклад от (₽)',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
            [
                'key' => 'salary_max',
                'name' => 'Оклад до (₽)',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 3,
                'rules' => []
            ],
        ];

        $gradesVersion = $this->upsertVersion($grades->id, $gradesSchema);

        $this->upsertItems($gradesVersion->id, [
            [
                'key' => 'grade-1',
                'data' => ['name' => 'Junior', 'level' => 1, 'salary_min' => 80000, 'salary_max' => 120000]
            ],
            [
                'key' => 'grade-2',
                'data' => ['name' => 'Middle', 'level' => 2, 'salary_min' => 130000, 'salary_max' => 200000]
            ],
            [
                'key' => 'grade-3',
                'data' => ['name' => 'Senior', 'level' => 3, 'salary_min' => 210000, 'salary_max' => 320000]
            ],
            [
                'key' => 'grade-4',
                'data' => ['name' => 'Lead', 'level' => 4, 'salary_min' => 330000, 'salary_max' => 450000]
            ],
            [
                'key' => 'grade-5',
                'data' => ['name' => 'Principal', 'level' => 5, 'salary_min' => 460000, 'salary_max' => null]
            ],
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
        $this->syncCategory($positions, $catOrg->id, $project->id);

        $positionsSchema = [
            [
                'key' => 'name',
                'name' => 'Должность',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'department',
                'name' => 'Подразделение',
                'type' => 'string',
                'nullable' => true,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'grade_min',
                'name' => 'Грейд от',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
            [
                'key' => 'grade_max',
                'name' => 'Грейд до',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 3,
                'rules' => []
            ],
        ];

        $positionsVersion = $this->upsertVersion($positions->id, $positionsSchema);

        $this->upsertItems($positionsVersion->id, [
            [
                'key' => 'pos-dev-junior',
                'data' => ['name' => 'Разработчик', 'department' => 'Разработка', 'grade_min' => 1, 'grade_max' => 2]
            ],
            [
                'key' => 'pos-dev-senior',
                'data' => [
                    'name' => 'Старший разработчик',
                    'department' => 'Разработка',
                    'grade_min' => 3,
                    'grade_max' => 4
                ]
            ],
            [
                'key' => 'pos-teamlead',
                'data' => ['name' => 'Тимлид', 'department' => 'Разработка', 'grade_min' => 4, 'grade_max' => 5]
            ],
            [
                'key' => 'pos-pm',
                'data' => [
                    'name' => 'Менеджер проекта',
                    'department' => 'Управление',
                    'grade_min' => 3,
                    'grade_max' => 5
                ]
            ],
            [
                'key' => 'pos-hr',
                'data' => ['name' => 'HR-менеджер', 'department' => 'HR', 'grade_min' => 2, 'grade_max' => 4]
            ],
            [
                'key' => 'pos-analyst',
                'data' => ['name' => 'Аналитик', 'department' => 'Аналитика', 'grade_min' => 2, 'grade_max' => 3]
            ],
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
        $this->syncCategory($departments, $catOrg->id, $project->id);

        $departmentsSchema = [
            [
                'key' => 'name',
                'name' => 'Название',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'code',
                'name' => 'Код',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'parent_name',
                'name' => 'Родительский отдел',
                'type' => 'string',
                'nullable' => true,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
        ];

        $departmentsVersion = $this->upsertVersion($departments->id, $departmentsSchema);

        $this->upsertItems($departmentsVersion->id, [
            ['key' => 'dept-dev', 'data' => ['name' => 'Разработка', 'code' => 'DEV', 'parent_name' => null]],
            ['key' => 'dept-hr', 'data' => ['name' => 'HR', 'code' => 'HR', 'parent_name' => null]],
            ['key' => 'dept-finance', 'data' => ['name' => 'Финансы', 'code' => 'FIN', 'parent_name' => null]],
            ['key' => 'dept-sales', 'data' => ['name' => 'Продажи', 'code' => 'SLS', 'parent_name' => null]],
            ['key' => 'dept-backend', 'data' => ['name' => 'Backend', 'code' => 'BE', 'parent_name' => 'Разработка']],
            ['key' => 'dept-frontend', 'data' => ['name' => 'Frontend', 'code' => 'FE', 'parent_name' => 'Разработка']],
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
        $this->syncCategory($technologies, $catDev->id, $project->id);

        $technologiesSchema = [
            [
                'key' => 'name',
                'name' => 'Название',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'type',
                'name' => 'Тип',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'level',
                'name' => 'Уровень',
                'type' => 'string',
                'nullable' => true,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
        ];

        $technologiesVersion = $this->upsertVersion($technologies->id, $technologiesSchema);

        $this->upsertItems($technologiesVersion->id, [
            ['key' => 'tech-php', 'data' => ['name' => 'PHP', 'type' => 'Backend', 'level' => 'Senior']],
            ['key' => 'tech-typescript', 'data' => ['name' => 'TypeScript', 'type' => 'Frontend', 'level' => 'Middle']],
            ['key' => 'tech-vue', 'data' => ['name' => 'Vue.js', 'type' => 'Frontend', 'level' => 'Middle']],
            ['key' => 'tech-react', 'data' => ['name' => 'React', 'type' => 'Frontend', 'level' => 'Middle']],
            ['key' => 'tech-laravel', 'data' => ['name' => 'Laravel', 'type' => 'Backend', 'level' => 'Senior']],
            ['key' => 'tech-docker', 'data' => ['name' => 'Docker', 'type' => 'DevOps', 'level' => 'Middle']],
            ['key' => 'tech-postgres', 'data' => ['name' => 'PostgreSQL', 'type' => 'Database', 'level' => 'Middle']],
            ['key' => 'tech-redis', 'data' => ['name' => 'Redis', 'type' => 'Database', 'level' => 'Junior']],
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
        $this->syncCategory($servers, $catInfra->id, $project->id);

        $serversSchema = [
            [
                'key' => 'name',
                'name' => 'Имя хоста',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'environment',
                'name' => 'Окружение',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'ip',
                'name' => 'IP-адрес',
                'type' => 'string',
                'nullable' => true,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
            [
                'key' => 'cpu',
                'name' => 'CPU (cores)',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 3,
                'rules' => []
            ],
            [
                'key' => 'ram_gb',
                'name' => 'RAM (GB)',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 4,
                'rules' => []
            ],
        ];

        $serversVersion = $this->upsertVersion($servers->id, $serversSchema);

        $this->upsertItems($serversVersion->id, [
            [
                'key' => 'srv-prod-web',
                'data' => [
                    'name' => 'prod-web-01',
                    'environment' => 'production',
                    'ip' => '10.0.1.10',
                    'cpu' => 8,
                    'ram_gb' => 32
                ]
            ],
            [
                'key' => 'srv-prod-db',
                'data' => [
                    'name' => 'prod-db-01',
                    'environment' => 'production',
                    'ip' => '10.0.1.20',
                    'cpu' => 16,
                    'ram_gb' => 64
                ]
            ],
            [
                'key' => 'srv-stage-web',
                'data' => [
                    'name' => 'stage-web-01',
                    'environment' => 'staging',
                    'ip' => '10.0.2.10',
                    'cpu' => 4,
                    'ram_gb' => 16
                ]
            ],
            [
                'key' => 'srv-dev-01',
                'data' => [
                    'name' => 'dev-shared-01',
                    'environment' => 'development',
                    'ip' => '10.0.3.10',
                    'cpu' => 4,
                    'ram_gb' => 8
                ]
            ],
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
        $this->syncCategory($taskStatuses, $catOps->id, $project->id);

        $taskStatusesSchema = [
            [
                'key' => 'name',
                'name' => 'Название',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'color',
                'name' => 'Цвет',
                'type' => 'string',
                'nullable' => true,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'is_final',
                'name' => 'Финальный',
                'type' => 'boolean',
                'nullable' => false,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
            [
                'key' => 'order',
                'name' => 'Порядок',
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'sort_order' => 3,
                'rules' => []
            ],
        ];

        $taskStatusesVersion = $this->upsertVersion($taskStatuses->id, $taskStatusesSchema);

        $this->upsertItems($taskStatusesVersion->id, [
            ['key' => 'ts-new', 'data' => ['name' => 'Новая', 'color' => '#94a3b8', 'is_final' => false, 'order' => 1]],
            [
                'key' => 'ts-in-progress',
                'data' => ['name' => 'В работе', 'color' => '#3b82f6', 'is_final' => false, 'order' => 2]
            ],
            [
                'key' => 'ts-review',
                'data' => ['name' => 'На проверке', 'color' => '#f59e0b', 'is_final' => false, 'order' => 3]
            ],
            [
                'key' => 'ts-done',
                'data' => ['name' => 'Выполнена', 'color' => '#10b981', 'is_final' => true, 'order' => 4]
            ],
            [
                'key' => 'ts-cancelled',
                'data' => ['name' => 'Отменена', 'color' => '#ef4444', 'is_final' => true, 'order' => 5]
            ],
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
        $this->syncCategory($priorities, $catOps->id, $project->id);

        $prioritiesSchema = [
            [
                'key' => 'name',
                'name' => 'Название',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'level',
                'name' => 'Уровень',
                'type' => 'integer',
                'nullable' => false,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'sla_hours',
                'name' => 'SLA (ч)',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
        ];

        $prioritiesVersion = $this->upsertVersion($priorities->id, $prioritiesSchema);

        $this->upsertItems($prioritiesVersion->id, [
            ['key' => 'prio-critical', 'data' => ['name' => 'Критический', 'level' => 1, 'sla_hours' => 2]],
            ['key' => 'prio-high', 'data' => ['name' => 'Высокий', 'level' => 2, 'sla_hours' => 8]],
            ['key' => 'prio-medium', 'data' => ['name' => 'Средний', 'level' => 3, 'sla_hours' => 24]],
            ['key' => 'prio-low', 'data' => ['name' => 'Низкий', 'level' => 4, 'sla_hours' => 72]],
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
        $this->syncCategory($offices, $catOrg->id, $project->id);

        $officesSchema = [
            [
                'key' => 'city',
                'name' => 'Город',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 0,
                'rules' => []
            ],
            [
                'key' => 'country',
                'name' => 'Страна',
                'type' => 'string',
                'nullable' => false,
                'default' => null,
                'sort_order' => 1,
                'rules' => []
            ],
            [
                'key' => 'address',
                'name' => 'Адрес',
                'type' => 'string',
                'nullable' => true,
                'default' => null,
                'sort_order' => 2,
                'rules' => []
            ],
            [
                'key' => 'capacity',
                'name' => 'Мест',
                'type' => 'integer',
                'nullable' => true,
                'default' => null,
                'sort_order' => 3,
                'rules' => []
            ],
        ];

        $officesVersion = $this->upsertVersion($offices->id, $officesSchema);

        $this->upsertItems($officesVersion->id, [
            [
                'key' => 'office-msk',
                'data' => [
                    'city' => 'Москва',
                    'country' => 'Россия',
                    'address' => 'ул. Льва Толстого, 16',
                    'capacity' => 250
                ]
            ],
            [
                'key' => 'office-spb',
                'data' => [
                    'city' => 'Санкт-Петербург',
                    'country' => 'Россия',
                    'address' => 'Невский пр., 100',
                    'capacity' => 80
                ]
            ],
            [
                'key' => 'office-nsk',
                'data' => [
                    'city' => 'Новосибирск',
                    'country' => 'Россия',
                    'address' => 'ул. Ленина, 52',
                    'capacity' => 40
                ]
            ],
            [
                'key' => 'office-kzn',
                'data' => ['city' => 'Казань', 'country' => 'Россия', 'address' => 'ул. Баумана, 9', 'capacity' => 30]
            ],
        ]);

        $this->seedExtendedDirectories($project->id, [
            'hr' => $catHr->id,
            'it' => $catIt->id,
            'ops' => $catOps->id,
            'finance' => $catFinance->id,
            'sales' => $catSales->id,
            'logistics' => $catLogistics->id,
            'reference' => $catReference->id,
        ]);

        DirectoryCache::forgetList($project->id);
    }

    /** @param  array<string, string>  $categories */
    private function seedExtendedDirectories(string $projectId, array $categories): void
    {
        $this->seedDirectory(
            $projectId,
            9,
            'Страны мира',
            'countries',
            'Страны, телефонные коды и признаки доступности',
            $categories['reference'],
            [
                $this->field('name', 'Страна'),
                $this->field('iso2', 'ISO-2'),
                $this->field('calling_code', 'Телефонный код'),
                $this->field('is_available', 'Доступна', 'boolean'),
            ],
            $this->countryItems(),
        );

        $this->seedDirectory(
            $projectId,
            10,
            'География доставки',
            'delivery-geography',
            'Большое четырёхуровневое дерево: страны, регионы, города и районы',
            $categories['logistics'],
            [
                $this->field('name', 'Название'),
                $this->field('level', 'Уровень'),
                $this->field('code', 'Код'),
                $this->field('population', 'Население', 'integer', true),
                $this->field('is_active', 'Активен', 'boolean'),
            ],
            $this->geographyItems(),
        );

        $this->seedDirectory(
            $projectId,
            11,
            'Каталог товаров',
            'product-catalog',
            'Большой трёхуровневый каталог категорий, групп и товарных позиций',
            $categories['sales'],
            [
                $this->field('name', 'Название'),
                $this->field('kind', 'Тип узла'),
                $this->field('sku', 'Артикул', 'string', true),
                $this->field('price', 'Цена (₽)', 'integer', true),
                $this->field('available_from', 'Доступен с', 'date', true),
                $this->field('is_active', 'Активен', 'boolean'),
            ],
            $this->productItems(),
        );

        $this->seedDirectory(
            $projectId,
            12,
            'Валюты',
            'currencies',
            'Коды валют и параметры округления',
            $categories['finance'],
            [
                $this->field('name', 'Валюта'),
                $this->field('code', 'Код'),
                $this->field('symbol', 'Символ'),
                $this->field('decimals', 'Знаков после запятой', 'integer'),
                $this->field('is_base', 'Базовая', 'boolean'),
            ],
            $this->flatItems('currency', [
                ['Российский рубль', 'RUB', '₽', 2, true],
                ['Доллар США', 'USD', '$', 2, false],
                ['Евро', 'EUR', '€', 2, false],
                ['Китайский юань', 'CNY', '¥', 2, false],
                ['Казахстанский тенге', 'KZT', '₸', 2, false],
                ['Белорусский рубль', 'BYN', 'Br', 2, false],
                ['Японская иена', 'JPY', '¥', 0, false],
            ], ['name', 'code', 'symbol', 'decimals', 'is_base']),
        );

        $this->seedDirectory(
            $projectId,
            13,
            'Способы оплаты',
            'payment-methods',
            'Небольшой справочник способов оплаты с настройками комиссии',
            $categories['finance'],
            [
                $this->field('name', 'Способ оплаты'),
                $this->field('code', 'Код'),
                $this->field('fee_percent', 'Комиссия (%)', 'integer'),
                $this->field('requires_confirmation', 'Требует подтверждения', 'boolean'),
            ],
            $this->flatItems('payment', [
                ['Банковская карта', 'card', 2, true],
                ['СБП', 'sbp', 0, true],
                ['Банковский перевод', 'bank', 0, false],
                ['Наличные', 'cash', 0, false],
                ['Корпоративный счёт', 'corporate', 0, true],
                ['Подарочный сертификат', 'gift', 0, true],
            ], ['name', 'code', 'fee_percent', 'requires_confirmation']),
            ['allow_other' => true, 'other_label' => 'Другой способ оплаты', 'other_external_key' => 'payment-other'],
        );

        $this->seedDirectory(
            $projectId,
            14,
            'Склады',
            'warehouses',
            'Сеть складов с датами открытия и лимитами хранения',
            $categories['logistics'],
            [
                $this->field('name', 'Склад'),
                $this->field('city', 'Город'),
                $this->field('capacity', 'Вместимость', 'integer'),
                $this->field('opened_at', 'Дата открытия', 'date'),
                $this->field('works_24h', 'Круглосуточный', 'boolean'),
            ],
            $this->warehouseItems(),
        );

        $this->seedDirectory(
            $projectId,
            15,
            'Классификатор оборудования',
            'equipment-classifier',
            'Среднее трёхуровневое дерево классов, типов и моделей оборудования',
            $categories['it'],
            [
                $this->field('name', 'Название'),
                $this->field('kind', 'Уровень'),
                $this->field('vendor', 'Производитель', 'string', true),
                $this->field('warranty_months', 'Гарантия (мес.)', 'integer', true),
                $this->field('requires_service', 'Требует обслуживания', 'boolean'),
            ],
            $this->equipmentItems(),
        );

        $this->seedDirectory(
            $projectId,
            16,
            'Матрица навыков',
            'skills-matrix',
            'Большое дерево направлений, навыков и уровней владения',
            $categories['hr'],
            [
                $this->field('name', 'Навык или уровень'),
                $this->field('kind', 'Тип'),
                $this->field('level', 'Уровень', 'integer', true),
                $this->field('score', 'Минимальный балл', 'integer', true),
                $this->field('is_required', 'Обязательный', 'boolean'),
            ],
            $this->skillItems(),
        );

        $this->seedDirectory(
            $projectId,
            17,
            'Категории инцидентов',
            'incident-categories',
            'Небольшое двухуровневое дерево типов обращений',
            $categories['ops'],
            [
                $this->field('name', 'Категория'),
                $this->field('code', 'Код'),
                $this->field('severity', 'Критичность', 'integer'),
                $this->field('requires_escalation', 'Нужна эскалация', 'boolean'),
            ],
            $this->incidentItems(),
        );

        $this->seedDirectory(
            $projectId,
            18,
            'Производственный календарь',
            'production-calendar',
            'Праздничные и сокращённые рабочие дни',
            $categories['hr'],
            [
                $this->field('name', 'Событие'),
                $this->field('date', 'Дата', 'date'),
                $this->field('is_day_off', 'Выходной', 'boolean'),
                $this->field('work_hours', 'Рабочих часов', 'integer'),
            ],
            $this->calendarItems(),
        );

        $this->seedDirectory(
            $projectId,
            19,
            'Политики SLA',
            'sla-policies',
            'Нормативы реакции и решения по уровням критичности',
            $categories['ops'],
            [
                $this->field('name', 'Политика'),
                $this->field('priority', 'Приоритет'),
                $this->field('response_minutes', 'Реакция (мин.)', 'integer'),
                $this->field('resolution_minutes', 'Решение (мин.)', 'integer'),
                $this->field('valid_from', 'Действует с', 'datetime'),
                $this->field('business_hours_only', 'Только рабочее время', 'boolean'),
            ],
            $this->slaItems(),
        );

        $this->seedDirectory(
            $projectId,
            20,
            'Каналы связи',
            'contact-channels',
            'Каналы коммуникации с клиентом',
            $categories['sales'],
            [
                $this->field('name', 'Канал'),
                $this->field('code', 'Код'),
                $this->field('supports_files', 'Поддерживает файлы', 'boolean'),
                $this->field('max_message_length', 'Макс. длина сообщения', 'integer', true),
            ],
            $this->flatItems('channel', [
                ['Телефон', 'phone', false, null],
                ['Электронная почта', 'email', true, null],
                ['SMS', 'sms', false, 70],
                ['Telegram', 'telegram', true, 4096],
                ['WhatsApp', 'whatsapp', true, 4096],
                ['Веб-чат', 'webchat', true, 10000],
            ], ['name', 'code', 'supports_files', 'max_message_length']),
            ['allow_other' => true, 'other_label' => 'Другой канал', 'other_external_key' => 'channel-other'],
        );
    }

    /** @param  array<string, mixed>  $attributes */
    private function upsertDirectory(string $projectId, array $attributes): Directory
    {
        /** @var Directory $dir */
        $dir = Directory::query()->updateOrCreate(
            ['id' => $attributes['id']],
            array_merge($attributes, ['project_id' => $projectId]),
        );

        return $dir;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<int, array{key: string, data: array<string, mixed>, parent_key?: string}>  $items
     * @param  array<string, mixed>  $versionAttributes
     */
    private function seedDirectory(
        string $projectId,
        int $number,
        string $name,
        string $slug,
        string $description,
        string $categoryId,
        array $schema,
        array $items,
        array $versionAttributes = [],
    ): void {
        $directory = $this->upsertDirectory($projectId, [
            'id' => sprintf('019f0000-0000-7000-b000-%012d', $number),
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'source_type' => 'manual',
            'match_by' => 'name',
            'default_sort' => 'name',
            'sync_status' => 'idle',
        ]);
        $this->syncCategory($directory, $categoryId, $projectId);

        $orderedSchema = array_map(
            static fn(array $field, int $index): array => [...$field, 'sort_order' => $index],
            $schema,
            array_keys($schema),
        );
        $version = $this->upsertVersion($directory->id, $orderedSchema, $versionAttributes);
        $this->upsertItems($version->id, $items);
    }

    /** @return array<string, mixed> */
    private function field(
        string $key,
        string $name,
        string $type = 'string',
        bool $nullable = false,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'type' => $type,
            'nullable' => $nullable,
            'default' => null,
            'sort_order' => 0,
            'filterable' => true,
            'searchable' => $type === 'string',
            'rules' => [
                $nullable ? 'nullable' : 'required',
                match ($type) {
                    'integer' => 'integer',
                    'boolean' => 'boolean',
                    'date', 'datetime' => 'date',
                    default => 'string',
                }
            ],
        ];
    }

    private function syncCategory(Directory $directory, string $categoryId, string $projectId): void
    {
        $directory->categories()->sync([
            $categoryId => ['project_id' => $projectId],
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, data: array<string, mixed>}>
     */
    private function flatItems(string $prefix, array $rows, array $keys): array
    {
        return array_map(
            static fn(array $row, int $index): array => [
                'key' => sprintf('%s-%03d', $prefix, $index + 1),
                'data' => array_combine($keys, $row),
            ],
            $rows,
            array_keys($rows),
        );
    }

    /** @return array<int, array{key: string, data: array<string, mixed>}> */
    private function countryItems(): array
    {
        return $this->flatItems('country', [
            ['Россия', 'RU', '+7', true],
            ['Беларусь', 'BY', '+375', true],
            ['Казахстан', 'KZ', '+7', true],
            ['Армения', 'AM', '+374', true],
            ['Кыргызстан', 'KG', '+996', true],
            ['Узбекистан', 'UZ', '+998', true],
            ['Азербайджан', 'AZ', '+994', true],
            ['Грузия', 'GE', '+995', false],
            ['Китай', 'CN', '+86', true],
            ['Турция', 'TR', '+90', true],
            ['Сербия', 'RS', '+381', false],
            ['Индия', 'IN', '+91', false],
        ], ['name', 'iso2', 'calling_code', 'is_available']);
    }

    /** @return array<int, array{key: string, data: array<string, mixed>, parent_key?: string}> */
    private function geographyItems(): array
    {
        $items = [];
        $countries = ['Россия' => 'RU', 'Казахстан' => 'KZ', 'Беларусь' => 'BY'];

        foreach ($countries as $countryName => $countryCode) {
            $countryKey = "geo-{$countryCode}";
            $items[] = [
                'key' => $countryKey,
                'data' => [
                    'name' => $countryName,
                    'level' => 'country',
                    'code' => $countryCode,
                    'population' => null,
                    'is_active' => true,
                ]
            ];

            for ($region = 1; $region <= 5; $region++) {
                $regionKey = sprintf('%s-r%02d', $countryKey, $region);
                $items[] = [
                    'key' => $regionKey,
                    'parent_key' => $countryKey,
                    'data' => [
                        'name' => "Регион {$region} ({$countryCode})",
                        'level' => 'region',
                        'code' => sprintf('%s-%02d', $countryCode, $region),
                        'population' => 500000 + ($region * 175000),
                        'is_active' => true,
                    ]
                ];

                for ($city = 1; $city <= 6; $city++) {
                    $cityKey = sprintf('%s-c%02d', $regionKey, $city);
                    $items[] = [
                        'key' => $cityKey,
                        'parent_key' => $regionKey,
                        'data' => [
                            'name' => "Город {$region}.{$city}",
                            'level' => 'city',
                            'code' => sprintf('%s-%02d-%02d', $countryCode, $region, $city),
                            'population' => 50000 + ($city * 23000),
                            'is_active' => $city !== 6,
                        ]
                    ];

                    for ($district = 1; $district <= 3; $district++) {
                        $items[] = [
                            'key' => sprintf('%s-d%02d', $cityKey, $district),
                            'parent_key' => $cityKey,
                            'data' => [
                                'name' => "Район {$district}",
                                'level' => 'district',
                                'code' => sprintf('%s-%02d-%02d-%02d', $countryCode, $region, $city, $district),
                                'population' => 10000 + ($district * 7000),
                                'is_active' => true,
                            ],
                        ];
                    }
                }
            }
        }

        return $items;
    }

    /** @return array<int, array{key: string, data: array<string, mixed>, parent_key?: string}> */
    private function productItems(): array
    {
        $items = [];
        $categories = ['Электроника', 'Дом и сад', 'Офис', 'Автотовары', 'Спорт', 'Красота'];

        foreach ($categories as $categoryIndex => $categoryName) {
            $categoryKey = sprintf('product-category-%02d', $categoryIndex + 1);
            $items[] = [
                'key' => $categoryKey,
                'data' => [
                    'name' => $categoryName,
                    'kind' => 'category',
                    'sku' => null,
                    'price' => null,
                    'available_from' => null,
                    'is_active' => true,
                ]
            ];

            for ($group = 1; $group <= 4; $group++) {
                $groupKey = sprintf('%s-group-%02d', $categoryKey, $group);
                $items[] = [
                    'key' => $groupKey,
                    'parent_key' => $categoryKey,
                    'data' => [
                        'name' => "{$categoryName}: группа {$group}",
                        'kind' => 'group',
                        'sku' => null,
                        'price' => null,
                        'available_from' => null,
                        'is_active' => true,
                    ]
                ];

                for ($product = 1; $product <= 8; $product++) {
                    $number = (($categoryIndex * 4 + $group - 1) * 8) + $product;
                    $items[] = [
                        'key' => sprintf('%s-item-%03d', $groupKey, $product),
                        'parent_key' => $groupKey,
                        'data' => [
                            'name' => "Товар {$number}",
                            'kind' => 'product',
                            'sku' => sprintf('SKU-%05d', $number),
                            'price' => 490 + ($number * 135),
                            'available_from' => sprintf(
                                '2026-%02d-%02d',
                                (($number - 1) % 12) + 1,
                                (($number - 1) % 28) + 1
                            ),
                            'is_active' => $product !== 8,
                        ],
                    ];
                }
            }
        }

        return $items;
    }

    /** @return array<int, array{key: string, data: array<string, mixed>}> */
    private function warehouseItems(): array
    {
        $cities = ['Москва', 'Санкт-Петербург', 'Казань', 'Екатеринбург', 'Новосибирск', 'Ростов-на-Дону'];
        $items = [];

        for ($index = 1; $index <= 30; $index++) {
            $city = $cities[($index - 1) % count($cities)];
            $items[] = [
                'key' => sprintf('warehouse-%03d', $index),
                'data' => [
                    'name' => sprintf('Склад %s-%02d', mb_substr($city, 0, 3), $index),
                    'city' => $city,
                    'capacity' => 1000 + ($index * 275),
                    'opened_at' => sprintf('20%02d-%02d-01', 10 + ($index % 16), (($index - 1) % 12) + 1),
                    'works_24h' => $index % 3 === 0,
                ],
            ];
        }

        return $items;
    }

    /** @return array<int, array{key: string, data: array<string, mixed>, parent_key?: string}> */
    private function equipmentItems(): array
    {
        $items = [];
        $classes = ['Серверное оборудование', 'Рабочие станции', 'Сетевая техника', 'Периферия'];
        $vendors = ['Delta', 'Orion', 'Vector', 'Atlas'];

        foreach ($classes as $classIndex => $className) {
            $classKey = sprintf('equipment-class-%02d', $classIndex + 1);
            $items[] = [
                'key' => $classKey,
                'data' => [
                    'name' => $className,
                    'kind' => 'class',
                    'vendor' => null,
                    'warranty_months' => null,
                    'requires_service' => true,
                ]
            ];

            for ($type = 1; $type <= 4; $type++) {
                $typeKey = sprintf('%s-type-%02d', $classKey, $type);
                $items[] = [
                    'key' => $typeKey,
                    'parent_key' => $classKey,
                    'data' => [
                        'name' => "Тип {$type}",
                        'kind' => 'type',
                        'vendor' => null,
                        'warranty_months' => null,
                        'requires_service' => true,
                    ]
                ];

                for ($model = 1; $model <= 4; $model++) {
                    $items[] = [
                        'key' => sprintf('%s-model-%02d', $typeKey, $model),
                        'parent_key' => $typeKey,
                        'data' => [
                            'name' => sprintf('Модель %d-%d-%d', $classIndex + 1, $type, $model),
                            'kind' => 'model',
                            'vendor' => $vendors[($model - 1) % count($vendors)],
                            'warranty_months' => 12 * $model,
                            'requires_service' => $model % 2 === 0,
                        ],
                    ];
                }
            }
        }

        return $items;
    }

    /** @return array<int, array{key: string, data: array<string, mixed>, parent_key?: string}> */
    private function skillItems(): array
    {
        $items = [];
        $domains = ['Разработка', 'Аналитика', 'Управление', 'Продажи', 'Поддержка', 'DevOps'];

        foreach ($domains as $domainIndex => $domainName) {
            $domainKey = sprintf('skill-domain-%02d', $domainIndex + 1);
            $items[] = [
                'key' => $domainKey,
                'data' => [
                    'name' => $domainName,
                    'kind' => 'domain',
                    'level' => null,
                    'score' => null,
                    'is_required' => false,
                ]
            ];

            for ($skill = 1; $skill <= 6; $skill++) {
                $skillKey = sprintf('%s-skill-%02d', $domainKey, $skill);
                $items[] = [
                    'key' => $skillKey,
                    'parent_key' => $domainKey,
                    'data' => [
                        'name' => "{$domainName}: навык {$skill}",
                        'kind' => 'skill',
                        'level' => null,
                        'score' => null,
                        'is_required' => $skill <= 2,
                    ]
                ];

                for ($level = 1; $level <= 3; $level++) {
                    $items[] = [
                        'key' => sprintf('%s-level-%02d', $skillKey, $level),
                        'parent_key' => $skillKey,
                        'data' => [
                            'name' => "Уровень {$level}",
                            'kind' => 'level',
                            'level' => $level,
                            'score' => $level * 30,
                            'is_required' => $skill <= 2,
                        ],
                    ];
                }
            }
        }

        return $items;
    }

    /** @return array<int, array{key: string, data: array<string, mixed>, parent_key?: string}> */
    private function incidentItems(): array
    {
        $items = [];
        $groups = ['Инфраструктура', 'Приложения', 'Доступы', 'Рабочее место'];

        foreach ($groups as $groupIndex => $groupName) {
            $groupKey = sprintf('incident-group-%02d', $groupIndex + 1);
            $items[] = [
                'key' => $groupKey,
                'data' => [
                    'name' => $groupName,
                    'code' => sprintf('INC-%02d', $groupIndex + 1),
                    'severity' => $groupIndex + 1,
                    'requires_escalation' => $groupIndex < 2,
                ]
            ];

            for ($type = 1; $type <= 4; $type++) {
                $items[] = [
                    'key' => sprintf('%s-type-%02d', $groupKey, $type),
                    'parent_key' => $groupKey,
                    'data' => [
                        'name' => "{$groupName}: тип {$type}",
                        'code' => sprintf('INC-%02d-%02d', $groupIndex + 1, $type),
                        'severity' => min(4, $groupIndex + $type),
                        'requires_escalation' => $type === 1,
                    ],
                ];
            }
        }

        return $items;
    }

    /** @return array<int, array{key: string, data: array<string, mixed>}> */
    private function calendarItems(): array
    {
        $items = [];
        $events = [
            ['Новогодние каникулы', '01-01', true, 0],
            ['Рождество', '01-07', true, 0],
            ['День защитника Отечества', '02-23', true, 0],
            ['Международный женский день', '03-08', true, 0],
            ['Праздник Весны и Труда', '05-01', true, 0],
            ['День Победы', '05-09', true, 0],
            ['День России', '06-12', true, 0],
            ['День народного единства', '11-04', true, 0],
            ['Предпраздничный день', '12-31', false, 7],
        ];

        foreach ([2026, 2027] as $year) {
            foreach ($events as $index => [$name, $date, $isDayOff, $workHours]) {
                $items[] = [
                    'key' => sprintf('calendar-%d-%02d', $year, $index + 1),
                    'data' => [
                        'name' => $name,
                        'date' => "{$year}-{$date}",
                        'is_day_off' => $isDayOff,
                        'work_hours' => $workHours,
                    ],
                ];
            }
        }

        return $items;
    }

    /** @return array<int, array{key: string, data: array<string, mixed>}> */
    private function slaItems(): array
    {
        $items = [];

        foreach (
            [
                ['P1 Критический', 'P1', 15, 120, false],
                ['P2 Высокий', 'P2', 30, 240, false],
                ['P3 Средний', 'P3', 120, 1440, true],
                ['P4 Низкий', 'P4', 480, 4320, true],
                ['VIP критический', 'VIP-1', 5, 60, false],
                ['VIP стандартный', 'VIP-2', 20, 180, false],
                ['Внутренний', 'INT', 240, 2880, true],
                ['Плановый', 'PLAN', 1440, 10080, true],
            ] as $index => [$name, $priority, $response, $resolution, $businessHours]
        ) {
            $items[] = [
                'key' => sprintf('sla-%02d', $index + 1),
                'data' => [
                    'name' => $name,
                    'priority' => $priority,
                    'response_minutes' => $response,
                    'resolution_minutes' => $resolution,
                    'valid_from' => '2026-01-01 00:00:00',
                    'business_hours_only' => $businessHours,
                ],
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $attributes
     */
    private function upsertVersion(string|int $directoryId, array $schema, array $attributes = []): DirectoryVersion
    {
        /** @var DirectoryVersion $version */
        $version = DirectoryVersion::query()->updateOrCreate(
            ['directory_id' => $directoryId, 'version_number' => 1],
            array_merge([
                'status' => 'ready',
                'is_active' => true,
                'schema_json' => $schema,
            ], $attributes),
        );

        return $version;
    }

    /**
     * @param  array<int, array{key: string, data: array<string, mixed>, parent_key?: string}>  $items
     */
    private function upsertItems(int $versionId, array $items): void
    {
        /** @var array<string, int> $idsByKey */
        $idsByKey = [];

        foreach ($items as $item) {
            $search = $item['data']
                    |> array_values(...)
                    |> (fn($x) => array_filter($x, 'is_string'))
                    |> (fn($x) => implode(' ', $x));
            $parentKey = $item['parent_key'] ?? null;
            $parentId = is_string($parentKey)
                ? ($idsByKey[$parentKey] ?? DirectoryItem::query()
                    ->where('directory_version_id', $versionId)
                    ->where('external_key', $parentKey)
                    ->value('id'))
                : null;

            $directoryItem = DirectoryItem::query()->updateOrCreate(
                ['directory_version_id' => $versionId, 'external_key' => $item['key']],
                ['parent_id' => $parentId, 'data_json' => $item['data'], 'search_text' => $search],
            );
            $idsByKey[$item['key']] = $directoryItem->id;
        }
    }
}
