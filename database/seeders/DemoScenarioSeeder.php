<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Module\Projects\Models\Project;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

final class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $project1 = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY)->firstOrFail();
        $project2 = Project::query()->where('sitekey', DemoProjectSeeder::SITEKEY_2)->firstOrFail();

        // ── Категории: Demo Site ──────────────────────────────────────────────

        $catHr = $this->upsertCategory('019f0000-0000-7000-c000-000000000001', 'HR', null, $project1->id);
        $catOnboarding = $this->upsertCategory('019f0000-0000-7000-c000-000000000002', 'Онбординг', $catHr->id, $project1->id);
        $catRecruit = $this->upsertCategory('019f0000-0000-7000-c000-000000000003', 'Подбор персонала', $catHr->id, $project1->id);

        $catIt = $this->upsertCategory('019f0000-0000-7000-c000-000000000010', 'IT', null, $project1->id);
        $catIncident = $this->upsertCategory('019f0000-0000-7000-c000-000000000011', 'Инциденты', $catIt->id, $project1->id);
        $catRelease = $this->upsertCategory('019f0000-0000-7000-c000-000000000012', 'Релизы', $catIt->id, $project1->id);

        $catOps = $this->upsertCategory('019f0000-0000-7000-c000-000000000020', 'Операции', null, $project1->id);
        $catSupport = $this->upsertCategory('019f0000-0000-7000-c000-000000000021', 'Поддержка', $catOps->id, $project1->id);

        // ── Категории: Alpha Site ─────────────────────────────────────────────

        $catSales = $this->upsertCategory('019f0000-0000-7000-c000-000000000030', 'Продажи', null, $project2->id);
        $catLead = $this->upsertCategory('019f0000-0000-7000-c000-000000000031', 'Лиды', $catSales->id, $project2->id);
        $catClient = $this->upsertCategory('019f0000-0000-7000-c000-000000000032', 'Клиенты', $catSales->id, $project2->id);

        // ── Сценарии: Demo Site / HR / Онбординг ──────────────────────────────

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000001',
            name: 'Онбординг нового сотрудника',
            description: 'Полный цикл введения нового сотрудника: документы, оборудование, доступы, знакомство с командой.',
            tag: 'hr',
            projectId: $project1->id,
            categoryIds: [$catOnboarding->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000002',
            name: 'Выход сотрудника из компании',
            description: 'Оффбординг: возврат оборудования, закрытие доступов, передача дел.',
            tag: 'hr',
            projectId: $project1->id,
            categoryIds: [$catOnboarding->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000003',
            name: 'Согласование отпуска',
            description: 'Подача заявки на отпуск, согласование с руководителем и HR, уведомление команды.',
            tag: 'hr',
            projectId: $project1->id,
            categoryIds: [$catHr->id],
        );

        // ── Сценарии: Demo Site / HR / Подбор персонала ───────────────────────

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000004',
            name: 'Скрининг резюме',
            description: 'Первичная проверка кандидатов: соответствие требованиям, автоматический отбор.',
            tag: 'hr',
            projectId: $project1->id,
            categoryIds: [$catRecruit->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000005',
            name: 'Проведение технического интервью',
            description: 'Назначение интервью, сбор обратной связи от интервьюеров, финальное решение.',
            tag: 'hr',
            projectId: $project1->id,
            categoryIds: [$catRecruit->id],
        );

        // ── Сценарии: Demo Site / IT / Инциденты ─────────────────────────────

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000010',
            name: 'Расследование P1-инцидента',
            description: 'Критический инцидент: эскалация, подключение дежурных, постмортем.',
            tag: 'it',
            projectId: $project1->id,
            categoryIds: [$catIncident->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000011',
            name: 'Обработка инцидента P2–P3',
            description: 'Стандартный инцидент: регистрация, назначение ответственного, разрешение.',
            tag: 'it',
            projectId: $project1->id,
            categoryIds: [$catIncident->id],
        );

        // ── Сценарии: Demo Site / IT / Релизы ────────────────────────────────

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000012',
            name: 'Деплой на продакшн',
            description: 'Пайплайн выпуска: проверки перед деплоем, canary-rollout, мониторинг после.',
            tag: 'it',
            projectId: $project1->id,
            categoryIds: [$catRelease->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000013',
            name: 'Откат релиза',
            description: 'Процедура rollback: остановка трафика, восстановление предыдущей версии, оповещение.',
            tag: 'it',
            projectId: $project1->id,
            categoryIds: [$catRelease->id],
        );

        // ── Сценарии: Demo Site / Операции / Поддержка ───────────────────────

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000020',
            name: 'Обработка обращения в поддержку',
            description: 'Первая линия: классификация тикета, маршрутизация по специалистам, закрытие.',
            tag: 'support',
            projectId: $project1->id,
            categoryIds: [$catSupport->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000021',
            name: 'Эскалация тикета',
            description: 'Автоматическая эскалация при превышении SLA: уведомление, переназначение.',
            tag: 'support',
            projectId: $project1->id,
            categoryIds: [$catSupport->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000022',
            name: 'Сбор NPS после закрытия тикета',
            description: 'Отправка опроса удовлетворённости, обработка ответов, отчёт по метрикам.',
            tag: 'support',
            projectId: $project1->id,
            categoryIds: [$catOps->id],
            isActive: false,
        );

        // ── Сценарии: Alpha Site / Продажи ───────────────────────────────────

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000030',
            name: 'Квалификация лида',
            description: 'Оценка потенциала лида: BANT-критерии, автоматическая маршрутизация к менеджеру.',
            tag: 'sales',
            projectId: $project2->id,
            categoryIds: [$catLead->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000031',
            name: 'Повторное вовлечение лида',
            description: 'Реактивация «холодных» лидов: цепочка касаний, персонализированные офферы.',
            tag: 'sales',
            projectId: $project2->id,
            categoryIds: [$catLead->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000032',
            name: 'Онбординг нового клиента',
            description: 'Приветственная цепочка, настройка аккаунта, знакомство с продуктом.',
            tag: 'sales',
            projectId: $project2->id,
            categoryIds: [$catClient->id],
        );

        $this->upsertScenario(
            id: '019f0001-0000-7000-d000-000000000033',
            name: 'Продление договора',
            description: 'Автоматическое уведомление за 60/30/7 дней до окончания, подготовка КП.',
            tag: 'sales',
            projectId: $project2->id,
            categoryIds: [$catClient->id],
        );
    }

    private function upsertCategory(string $id, string $name, ?string $parentId, string $projectId): Category
    {
        /** @var Category $category */
        $category = Category::query()->updateOrCreate(
            ['id' => $id],
            ['name' => $name, 'parent_id' => $parentId, 'is_active' => true],
        );

        \Illuminate\Support\Facades\DB::table('model_has_categories')->updateOrInsert(
            [
                'category_id' => $category->id,
                'model_id'    => $category->id,
                'model_type'  => Scenario::class,
                'project_id'  => $projectId,
            ],
            ['created_at' => now(), 'updated_at' => now()],
        );

        return $category;
    }

    /**
     * @param string[] $categoryIds
     */
    private function upsertScenario(
        string $id,
        string $name,
        string $description,
        string $tag,
        string $projectId,
        array $categoryIds,
        bool $isActive = true,
    ): Scenario {
        /** @var Scenario $scenario */
        $scenario = Scenario::query()->updateOrCreate(
            ['id' => $id],
            [
                'name'        => $name,
                'description' => $description,
                'is_active'   => $isActive,
                'tags'        => [$tag],
                'project_id'  => $projectId,
            ],
        );

        $pivot = [];
        foreach ($categoryIds as $catId) {
            $pivot[$catId] = ['project_id' => $projectId];
        }
        $scenario->categories()->sync($pivot);

        $versionId = '019f0002-0000-7000-' . substr($id, -17);

        /** @var ScenarioVersion $version */
        $version = ScenarioVersion::query()->updateOrCreate(
            ['id' => $versionId],
            [
                'scenario_id' => $scenario->id,
                'project_id'  => $projectId,
                'name'        => 'v1',
                'status'      => 'active',
            ],
        );

        ScenarioVersionRevision::query()->updateOrCreate(
            ['scenario_version_id' => $version->id],
            [
                'schema_json'    => ['nodes' => [], 'edges' => []],
                'nodes_json'     => [],
                'edges_json'     => [],
                'schema_version' => 1,
            ],
        );

        $scenario->update(['active_version_id' => $version->id]);

        return $scenario->fresh() ?? $scenario;
    }
}
