<?php

declare(strict_types=1);

namespace Database\Seeders;

use Module\Users\Models\Role;
use Module\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Module\Projects\Models\Project;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

final class E2eSeeder extends Seeder
{
    public const string EMAIL = 'e2e@scenario.local';

    public const string PASSWORD = 'e2e-password';

    public const string SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000001';

    private const string VERSION_ID = '019f0e2e-0000-7000-a000-000000000002';

    public const string CONDITION_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000010';

    private const string CONDITION_VERSION_ID = '019f0e2e-0000-7000-a000-000000000011';

    public const string VARIABLE_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000020';

    private const string VARIABLE_VERSION_ID = '019f0e2e-0000-7000-a000-000000000021';

    public const string MANUAL_CONDITION_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000030';

    private const string MANUAL_CONDITION_VERSION_ID = '019f0e2e-0000-7000-a000-000000000031';

    public const string ACTION_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000040';

    private const string ACTION_VERSION_ID = '019f0e2e-0000-7000-a000-000000000041';

    public const string LINK_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000050';

    private const string LINK_VERSION_ID = '019f0e2e-0000-7000-a000-000000000051';

    public const string LINK_TARGET_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000052';

    private const string LINK_TARGET_VERSION_ID = '019f0e2e-0000-7000-a000-000000000053';

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            DemoProjectSeeder::class,
        ]);

        $project = Project::query()
            ->where('sitekey', DemoProjectSeeder::SITEKEY)
            ->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL, 'project_id' => $project->id],
            [
                'name' => 'E2E User',
                'login' => 'e2e-user',
                'sitekey' => $project->sitekey,
                'host' => $project->host,
                'password' => Hash::make(self::PASSWORD),
            ],
        );

        $role = Role::query()->where('name', AdminUserSeeder::ROLE)->first();
        if ($role !== null) {
            $user->syncRoles([$role]);
        }

        $scenario = Scenario::query()->updateOrCreate(
            ['id' => self::SCENARIO_ID],
            [
                'project_id' => $project->id,
                'name' => 'E2E: простой опрос',
                'description' => 'Стабильный сценарий для Playwright.',
                'status' => 'active',
                'is_active' => true,
                'alias' => 'e2e-simple-survey',
                'tags' => ['e2e'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );

        $version = ScenarioVersion::query()->updateOrCreate(
            ['id' => self::VERSION_ID],
            [
                'scenario_id' => $scenario->id,
                'project_id' => $project->id,
                'name' => 'E2E v1',
                'status' => 'active',
            ],
        );

        $nodes = $this->nodes();
        $edges = $this->edges();

        ScenarioVersionRevision::query()->updateOrCreate(
            ['scenario_version_id' => $version->id],
            [
                'schema_json' => [
                    'format' => 'scenario-flow',
                    'version' => 1,
                    'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
                    'blocks' => $nodes,
                    'connections' => $edges,
                ],
                'nodes_json' => $nodes,
                'edges_json' => $edges,
                'schema_version' => 1,
                'created_at' => now(),
            ],
        );

        $scenario->forceFill(['active_version_id' => $version->id])->save();

        $this->seedConditionScenario($project, $user);
        $this->seedVariableScenario($project, $user);
        $this->seedManualConditionScenario($project, $user);
        $this->seedActionScenario($project, $user);
        $this->seedScenarioLinkScenarios($project, $user);

        $user->tokens()->where('name', 'api')->delete();
    }

    private function seedConditionScenario(Project $project, User $user): void
    {
        $scenario = Scenario::query()->updateOrCreate(
            ['id' => self::CONDITION_SCENARIO_ID],
            [
                'project_id' => $project->id,
                'name' => 'E2E: condition и action',
                'description' => 'Проверка математического condition и автоматической action-ноды.',
                'status' => 'active',
                'is_active' => true,
                'alias' => 'e2e-condition-action',
                'tags' => ['e2e'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );

        $version = ScenarioVersion::query()->updateOrCreate(
            ['id' => self::CONDITION_VERSION_ID],
            [
                'scenario_id' => $scenario->id,
                'project_id' => $project->id,
                'name' => 'E2E condition v1',
                'status' => 'active',
            ],
        );

        $nodes = [
            ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            [
                'id' => 'calculation',
                'type' => 'block',
                'position' => ['x' => 200, 'y' => 0],
                'data' => [
                    'title' => 'Расчёт заказа',
                    'fields' => [
                        $this->numberField('price', 'Цена', 'price'),
                        $this->numberField('quantity', 'Количество', 'quantity'),
                        $this->numberField('discount', 'Скидка', 'discount'),
                    ],
                ],
            ],
            [
                'id' => 'condition',
                'type' => 'condition',
                'position' => ['x' => 400, 'y' => 0],
                'data' => [
                    'mode' => 'auto',
                    'expression' => '(price * quantity) - discount',
                    'rules' => [
                        [
                            'operator' => 'greater_or_equal',
                            'value' => 100,
                            'targetNodeId' => 'action',
                        ],
                    ],
                    'fallbackTargetNodeId' => 'small_end',
                ],
            ],
            [
                'id' => 'action',
                'type' => 'action',
                'position' => ['x' => 600, 'y' => -80],
                'data' => [
                    'skipInSurvey' => true,
                    'execution_mode' => 'sequential',
                    'action_items' => [],
                ],
            ],
            [
                'id' => 'large_end',
                'type' => 'end',
                'position' => ['x' => 800, 'y' => -80],
                'data' => [
                    'title' => 'Крупный заказ',
                    'description' => '<p>Сумма: {{ price * quantity - discount }}</p>',
                    'blocks' => [],
                ],
            ],
            [
                'id' => 'small_end',
                'type' => 'end',
                'position' => ['x' => 600, 'y' => 100],
                'data' => [
                    'title' => 'Обычный заказ',
                    'description' => '<p>Сумма меньше 100</p>',
                    'blocks' => [],
                ],
            ],
        ];
        $edges = [
            ['id' => 'start-calculation', 'source' => 'start', 'target' => 'calculation'],
            ['id' => 'calculation-condition', 'source' => 'calculation', 'target' => 'condition'],
            ['id' => 'condition-action', 'source' => 'condition', 'target' => 'action'],
            ['id' => 'condition-small', 'source' => 'condition', 'target' => 'small_end'],
            ['id' => 'action-large', 'source' => 'action', 'target' => 'large_end'],
        ];

        ScenarioVersionRevision::query()->updateOrCreate(
            ['scenario_version_id' => $version->id],
            [
                'schema_json' => [
                    'format' => 'scenario-flow',
                    'version' => 1,
                    'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
                    'blocks' => $nodes,
                    'connections' => $edges,
                ],
                'nodes_json' => $nodes,
                'edges_json' => $edges,
                'schema_version' => 1,
                'created_at' => now(),
            ],
        );

        $scenario->forceFill(['active_version_id' => $version->id])->save();
    }

    private function seedVariableScenario(Project $project, User $user): void
    {
        $scenario = Scenario::query()->updateOrCreate(
            ['id' => self::VARIABLE_SCENARIO_ID],
            [
                'project_id' => $project->id,
                'name' => 'E2E: переменные в player',
                'description' => 'Проверка подстановки {{ variable }} в заголовках, тексте и placeholder.',
                'status' => 'active',
                'is_active' => true,
                'alias' => 'e2e-player-variables',
                'tags' => ['e2e'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );

        $version = ScenarioVersion::query()->updateOrCreate(
            ['id' => self::VARIABLE_VERSION_ID],
            [
                'scenario_id' => $scenario->id,
                'project_id' => $project->id,
                'name' => 'E2E variables v1',
                'status' => 'active',
            ],
        );

        $nodes = [
            [
                'id' => 'start',
                'type' => 'start',
                'position' => ['x' => 0, 'y' => 0],
                'data' => ['title' => 'Начало'],
            ],
            [
                'id' => 'participant',
                'type' => 'block',
                'position' => ['x' => 240, 'y' => 0],
                'data' => [
                    'title' => 'Данные участника',
                    'skipInSurvey' => false,
                    'fields' => [
                        [
                            'id' => 'participant_name',
                            'type' => 'input',
                            'name' => 'participant_name',
                            'varName' => 'participant_name',
                            'label' => 'Имя участника',
                            'required' => true,
                            'placeholder' => 'Иван Петров',
                            'value' => '',
                        ],
                        [
                            'id' => 'participant_email',
                            'type' => 'email',
                            'name' => 'participant_email',
                            'varName' => 'participant_email',
                            'label' => 'Email участника',
                            'required' => true,
                            'placeholder' => 'ivan@example.test',
                            'value' => '',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'summary',
                'type' => 'block',
                'position' => ['x' => 500, 'y' => 0],
                'data' => [
                    'title' => 'Подтверждение для {{ participant_name }}',
                    'text' => '<p>Мы отправили письмо на {{ participant_email }}</p>',
                    'skipInSurvey' => false,
                    'fields' => [
                        [
                            'id' => 'followup_note',
                            'type' => 'input',
                            'name' => 'followup_note',
                            'varName' => 'followup_note',
                            'label' => 'Комментарий для {{ participant_name }}',
                            'required' => false,
                            'placeholder' => 'Напишите сообщение для {{ participant_name }}',
                            'value' => '',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'end',
                'type' => 'end',
                'position' => ['x' => 760, 'y' => 0],
                'data' => [
                    'title' => 'Финал для {{ participant_name }}',
                    'description' => '<p>Спасибо, {{ participant_name }}! Письмо ушло на {{ participant_email }}.</p>',
                    'blocks' => [],
                ],
            ],
        ];
        $edges = [
            ['id' => 'start-participant', 'source' => 'start', 'target' => 'participant'],
            ['id' => 'participant-summary', 'source' => 'participant', 'target' => 'summary'],
            ['id' => 'summary-end', 'source' => 'summary', 'target' => 'end'],
        ];

        ScenarioVersionRevision::query()->updateOrCreate(
            ['scenario_version_id' => $version->id],
            [
                'schema_json' => [
                    'format' => 'scenario-flow',
                    'version' => 1,
                    'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
                    'blocks' => $nodes,
                    'connections' => $edges,
                ],
                'nodes_json' => $nodes,
                'edges_json' => $edges,
                'schema_version' => 1,
                'created_at' => now(),
            ],
        );

        $scenario->forceFill(['active_version_id' => $version->id])->save();
    }

    private function seedManualConditionScenario(Project $project, User $user): void
    {
        $nodes = [
            ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            [
                'id' => 'choice',
                'type' => 'condition',
                'position' => ['x' => 240, 'y' => 0],
                'data' => [
                    'mode' => 'manual',
                    'question' => 'Какой маршрут выбрать?',
                    'options' => [
                        ['label' => 'Быстрый маршрут', 'targetNodeId' => 'fast_end'],
                        ['label' => 'Подробный маршрут', 'targetNodeId' => 'detailed_end'],
                    ],
                ],
            ],
            [
                'id' => 'fast_end',
                'type' => 'end',
                'position' => ['x' => 500, 'y' => -100],
                'data' => [
                    'title' => 'Быстрый маршрут завершён',
                    'description' => '<p>Выбран короткий путь.</p>',
                    'blocks' => [],
                ],
            ],
            [
                'id' => 'detailed_end',
                'type' => 'end',
                'position' => ['x' => 500, 'y' => 100],
                'data' => [
                    'title' => 'Подробный маршрут завершён',
                    'description' => '<p>Выбран подробный путь.</p>',
                    'blocks' => [],
                ],
            ],
        ];

        $this->persistScenario(
            $project,
            $user,
            self::MANUAL_CONDITION_SCENARIO_ID,
            self::MANUAL_CONDITION_VERSION_ID,
            'E2E: ручное условие',
            'e2e-manual-condition',
            $nodes,
            [
                ['id' => 'start-choice', 'source' => 'start', 'target' => 'choice'],
                ['id' => 'choice-fast', 'source' => 'choice', 'target' => 'fast_end'],
                ['id' => 'choice-detailed', 'source' => 'choice', 'target' => 'detailed_end'],
            ],
        );
    }

    private function seedActionScenario(Project $project, User $user): void
    {
        $nodes = [
            ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            [
                'id' => 'prepare_action',
                'type' => 'action',
                'position' => ['x' => 200, 'y' => 0],
                'data' => [
                    'title' => 'Подготовка',
                    'skipInSurvey' => true,
                    'execution_mode' => 'sequential',
                    'action_items' => [],
                ],
            ],
            [
                'id' => 'confirmation',
                'type' => 'block',
                'position' => ['x' => 400, 'y' => 0],
                'data' => [
                    'title' => 'Подтверждение действия',
                    'skipInSurvey' => false,
                    'fields' => [
                        [
                            'id' => 'action_note',
                            'type' => 'input',
                            'name' => 'action_note',
                            'varName' => 'action_note',
                            'label' => 'Комментарий',
                            'required' => true,
                            'placeholder' => 'Введите комментарий',
                            'value' => '',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'finish_action',
                'type' => 'action',
                'position' => ['x' => 600, 'y' => 0],
                'data' => [
                    'title' => 'Завершение',
                    'skipInSurvey' => true,
                    'wait_for_result' => true,
                    'execution_mode' => 'sequential',
                    'action_items' => [],
                ],
            ],
            [
                'id' => 'end',
                'type' => 'end',
                'position' => ['x' => 800, 'y' => 0],
                'data' => [
                    'title' => 'Действия завершены',
                    'description' => '<p>Комментарий: {{ action_note }}</p>',
                    'blocks' => [],
                ],
            ],
        ];

        $this->persistScenario(
            $project,
            $user,
            self::ACTION_SCENARIO_ID,
            self::ACTION_VERSION_ID,
            'E2E: action-ноды',
            'e2e-action-nodes',
            $nodes,
            [
                ['id' => 'start-prepare', 'source' => 'start', 'target' => 'prepare_action'],
                ['id' => 'prepare-confirmation', 'source' => 'prepare_action', 'target' => 'confirmation'],
                ['id' => 'confirmation-finish', 'source' => 'confirmation', 'target' => 'finish_action'],
                ['id' => 'finish-end', 'source' => 'finish_action', 'target' => 'end'],
            ],
        );
    }

    private function seedScenarioLinkScenarios(Project $project, User $user): void
    {
        $targetNodes = [
            ['id' => 'target_start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            [
                'id' => 'linked_block',
                'type' => 'block',
                'position' => ['x' => 240, 'y' => 0],
                'data' => [
                    'title' => 'Шаг связанного сценария',
                    'skipInSurvey' => false,
                    'fields' => [
                        [
                            'id' => 'linked_value',
                            'type' => 'input',
                            'name' => 'linked_value',
                            'varName' => 'linked_value',
                            'label' => 'Значение связанного сценария',
                            'required' => true,
                            'placeholder' => 'Введите значение',
                            'value' => '',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'target_end',
                'type' => 'end',
                'position' => ['x' => 500, 'y' => 0],
                'data' => [
                    'title' => 'Связанный сценарий завершён',
                    'description' => '<p>Получено: {{ linked_block.linked_value }}</p>',
                    'blocks' => [],
                ],
            ],
        ];

        $this->persistScenario(
            $project,
            $user,
            self::LINK_TARGET_SCENARIO_ID,
            self::LINK_TARGET_VERSION_ID,
            'E2E: цель перехода',
            'e2e-link-target',
            $targetNodes,
            [
                ['id' => 'target-start-block', 'source' => 'target_start', 'target' => 'linked_block'],
                ['id' => 'target-block-end', 'source' => 'linked_block', 'target' => 'target_end'],
            ],
        );

        $sourceNodes = [
            ['id' => 'source_start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            [
                'id' => 'scenario_link',
                'type' => 'scenario_link',
                'position' => ['x' => 240, 'y' => 0],
                'data' => [
                    'targetScenarioId' => self::LINK_TARGET_SCENARIO_ID,
                    'targetVersionId' => self::LINK_TARGET_VERSION_ID,
                ],
            ],
        ];

        $this->persistScenario(
            $project,
            $user,
            self::LINK_SCENARIO_ID,
            self::LINK_VERSION_ID,
            'E2E: переход в сценарий',
            'e2e-scenario-link',
            $sourceNodes,
            [
                ['id' => 'source-start-link', 'source' => 'source_start', 'target' => 'scenario_link'],
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $edges
     */
    private function persistScenario(
        Project $project,
        User $user,
        string $scenarioId,
        string $versionId,
        string $name,
        string $alias,
        array $nodes,
        array $edges,
    ): void {
        $scenario = Scenario::query()->updateOrCreate(
            ['id' => $scenarioId],
            [
                'project_id' => $project->id,
                'name' => $name,
                'description' => 'Стабильный сценарий для Playwright.',
                'status' => 'active',
                'is_active' => true,
                'alias' => $alias,
                'tags' => ['e2e'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ],
        );

        $version = ScenarioVersion::query()->updateOrCreate(
            ['id' => $versionId],
            [
                'scenario_id' => $scenario->id,
                'project_id' => $project->id,
                'name' => 'E2E v1',
                'status' => 'active',
            ],
        );

        ScenarioVersionRevision::query()->updateOrCreate(
            ['scenario_version_id' => $version->id],
            [
                'schema_json' => [
                    'format' => 'scenario-flow',
                    'version' => 1,
                    'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
                    'blocks' => $nodes,
                    'connections' => $edges,
                ],
                'nodes_json' => $nodes,
                'edges_json' => $edges,
                'schema_version' => 1,
                'created_at' => now(),
            ],
        );

        $scenario->forceFill(['active_version_id' => $version->id])->save();
    }

    /** @return array<string, mixed> */
    private function numberField(string $name, string $label, string $varName): array
    {
        return [
            'id' => $name,
            'type' => 'number',
            'name' => $name,
            'varName' => $varName,
            'label' => $label,
            'required' => true,
            'placeholder' => '',
            'value' => null,
            'min' => null,
            'max' => null,
            'step' => 1,
            'decimalPlaces' => 0,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function nodes(): array
    {
        return [
            [
                'id' => 'start',
                'type' => 'start',
                'position' => ['x' => 0, 'y' => 0],
                'data' => ['title' => 'Начало'],
            ],
            [
                'id' => 'participant',
                'type' => 'block',
                'position' => ['x' => 250, 'y' => 0],
                'data' => [
                    'title' => 'Данные участника',
                    'skipInSurvey' => false,
                    'fields' => [
                        [
                            'id' => 'participant_name',
                            'type' => 'input',
                            'name' => 'participant_name',
                            'varName' => 'participant_name',
                            'label' => 'Имя участника',
                            'required' => true,
                            'placeholder' => 'Иван Петров',
                            'value' => '',
                        ],
                        [
                            'id' => 'participant_email',
                            'type' => 'email',
                            'name' => 'participant_email',
                            'varName' => 'participant_email',
                            'label' => 'Email участника',
                            'required' => true,
                            'placeholder' => 'ivan@example.test',
                            'value' => '',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'end',
                'type' => 'end',
                'position' => ['x' => 500, 'y' => 0],
                'data' => [
                    'title' => 'Опрос завершён',
                    'description' => '<p>Спасибо, {{ participant_name }}!</p>',
                    'blocks' => [],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function edges(): array
    {
        return [
            ['id' => 'start-participant', 'source' => 'start', 'target' => 'participant'],
            ['id' => 'participant-end', 'source' => 'participant', 'target' => 'end'],
        ];
    }
}
