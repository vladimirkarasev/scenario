<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\DTO\ScenarioStartData;
use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioRepository;
use Module\Scenario\Repositories\ScenarioRunRepository;
use Module\Scenario\Repositories\ScenarioRunStepRepository;
use Module\Scenario\Repositories\ScenarioRunUserRepository;
use Module\Scenario\Repositories\ScenarioVersionRepository;
use Module\Scenario\Repositories\ScenarioVersionRevisionRepository;
use Module\Scenario\Services\Nodes\NodeHandlerRegistry;
use Module\Scenario\Services\Nodes\RetryableNodeHandler;

final readonly class ScenarioPlayerService
{
    private const int MAX_STEPS = 100;

    private const int MAX_VISITS_PER_NODE = 10;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private NodeHandlerRegistry $nodeHandlers,
        private ScenarioRunStepManager $stepManager,
        private ScenarioRepository $scenarios,
        private ScenarioVersionRepository $versions,
        private ScenarioRunRepository $runs,
        private ScenarioRunStepRepository $steps,
        private ScenarioRunUserRepository $users,
        private ScenarioVersionRevisionRepository $revisions,
        private VariableResolver $variableResolver,
    ) {
    }

    /**
     * Плоские данные опроса: переменные из _variable_map уже подставлены,
     * служебные ключи (_player, _variable_map) убраны.
     *
     * @return array<string, mixed>
     */
    public function surveyData(ScenarioRun $run): array
    {
        $context = is_array($run->context) ? $run->context : [];
        $flat = $this->variableResolver->flatten($context);

        unset($flat[RunContextKeys::PLAYER], $flat[RunContextKeys::VARIABLE_MAP]);

        return $flat;
    }

    /** Запустить опрос по scenario_id / version_id / alias; вернуть id созданного прогона. */
    public function start(ScenarioStartData $data): string
    {
        $version = match (true) {
            $data->versionId !== null => ScenarioVersion::query()->findOrFail($data->versionId),
            $data->scenarioId !== null => $this->versions->resolveForScenario(
                $this->scenarios->findOrFail($data->scenarioId),
                null,
            ),
            default => $this->versions->resolveForScenario(
                Scenario::query()
                    ->where('alias', (string)$data->alias)
                    ->firstOrFail(),
                null,
            ),
        };

        return $this->createRun(
            new ScenarioRunData(
                scenarioId: $version->scenario_id,
                scenarioVersionId: $version->id,
                context: $data->context,
                userData: $data->userData,
            ),
        )->id;
    }

    /** Создать прогон по явным ID сценария и/или версии, автопродвинуть до первого интерактивного узла. */
    public function createRun(ScenarioRunData $data): ScenarioRun
    {
        if ($data->scenarioId !== null) {
            $scenario = $this->scenarios->findOrFail($data->scenarioId);
            $version = $this->versions->resolveForScenario($scenario, $data->scenarioVersionId);
        } else {
            $version = ScenarioVersion::query()->findOrFail($data->scenarioVersionId);
            $scenario = $this->scenarios->findOrFail($version->scenario_id);
        }

        $revision = $this->revisions->getLastRevision($version);
        $version->setRelation('latestRevision', $revision);

        $startNode = $this->graphResolver->findStartNode($version);

        $attributes = [
            'scenario_id' => $scenario->id,
            'scenario_version_id' => $version->id,
            'scenario_version_revision_id' => $revision?->id,
            'current_node_id' => $this->nodeId($startNode),
            'context' => $this->initializeContext(
                $data->context,
                $data->userData,
                $revision !== null ? ($revision->schema_json ?? []) : [],
            ),
            'status' => ScenarioRunStatus::Active->value,
        ];

        if ($data->userData !== []) {
            $user = $this->users->firstOrCreateFromRunData($data->userData);
            $attributes['created_by'] = $user->id;
            $attributes['updated_by'] = $user->id;
        }

        if ($data->operatorId !== null) {
            $attributes['operator_id'] = $data->operatorId;
        }

        return $this->progress($this->hydrateRun($this->runs->create($attributes)));
    }

    /** Загрузить прогон и продвинуть его до ближайшего интерактивного узла. */
    public function getRun(ScenarioRun $run): ScenarioRun
    {
        return $this->progress($this->hydrateRun($run));
    }

    /** Принять ввод пользователя на текущем узле и перейти к следующему. */
    public function continueRun(ScenarioRun $run, ScenarioRunContinueData $data): ScenarioRun
    {
        $run = $this->hydrateRun($run);

        if (!$this->isActive($run)) {
            return $run;
        }

        $node = $this->graphResolver->findNode($this->runVersion($run), (string)$run->current_node_id);
        $nextNodeId = $this->nodeHandlers->for($this->nodeType($node))->continueFrom($run, $node, $data);

        return $this->transition($run, $node, $nextNodeId, $data->input);
    }

    /**
     * Повторить выполнение текущей action-ноды с момента ошибки (кнопка «Повторить»).
     * Прогон остаётся на ноде; pipeline перезапускается с упавшей стадии.
     */
    public function retryActionNode(ScenarioRun $run): ScenarioRun
    {
        $run = $this->hydrateRun($run);

        if (!$this->isActive($run) || $run->current_node_id === null) {
            return $run;
        }

        $node = $this->graphResolver->findNode($this->runVersion($run), (string)$run->current_node_id);
        $handler = $this->nodeHandlers->for($this->nodeType($node));

        if ($handler instanceof RetryableNodeHandler) {
            $handler->retry($run, $node);
        }

        return $this->hydrateRun($run);
    }

    /**
     * Продвинуть прогон с асинхронной action-ноды к следующему узлу после завершения
     * цепочки экшенов. Вызывается из ResumeScenarioActionNodeJob по WebSocket-завершении.
     * Идемпотентно: если прогон уже ушёл с ноды или неактивен — ничего не делает.
     */
    public function resumeFromActionNode(ScenarioRun $run, string $nodeId): ScenarioRun
    {
        $run = $this->hydrateRun($run);

        if (!$this->isActive($run) || (string)$run->current_node_id !== $nodeId) {
            return $run;
        }

        $version = $this->runVersion($run);
        $node = $this->graphResolver->findNode($version, $nodeId);
        $nextNodeId = $this->graphResolver->defaultNextNodeId($version, $nodeId);

        return $this->transition($run, $node, $nextNodeId, []);
    }

    /** Откатиться к ранее посещённому узлу, сбросив историю с этой точки. */
    public function jumpRun(ScenarioRun $run, ScenarioRunJumpData $data): ScenarioRun
    {
        $run = $this->hydrateRun($run);
        $node = $this->graphResolver->findNode($this->runVersion($run), $data->nodeId);

        $targetStep = $this->steps->latestForNode($run, $this->nodeId($node));

        if ($targetStep !== null) {
            $this->steps->trimAfter($run, (int)$targetStep->id);
            $this->steps->update($targetStep, ['input' => null, 'output' => null, 'exited_at' => null]);
        } else {
            $this->stepManager->closeOpen($run, [], []);
        }

        $context = $run->context ?? [];
        $context[RunContextKeys::PLAYER] = ['total_steps' => 0, 'visited' => []];

        $run->forceFill([
            'status' => ScenarioRunStatus::Active,
            'current_node_id' => $this->nodeId($node),
            'context' => $context,
        ])->save();

        return $this->progress($this->hydrateRun($run));
    }

    /**
     * Сериализовать прогон в массив для API-ответа.
     *
     * @return array<string, mixed>
     */
    public function payload(ScenarioRun $run): array
    {
        $currentNode = null;
        $rendered = null;
        $version = $this->runVersion($run);
        $isFinished = in_array($run->status, [ScenarioRunStatus::Completed, ScenarioRunStatus::Failed], true);
        $operator = $run->operator;
        $renderContext = [
            ...($run->context ?? []),
            'run_id' => $run->id,
            'run_number' => $run->number,
            'run_number_formatted' => $run->formattedNumber(),
            'run_created_at' => $run->created_at?->format('d.m.Y H:i'),
            'run_completed_at' => $isFinished ? $run->updated_at?->format('d.m.Y H:i') : null,
            'operator_login' => $operator?->login,
            'operator_name' => $operator?->name,
            'operator_fio' => $operator?->fio,
            'project_name' => $operator?->project?->name,
        ];

        if ($run->current_node_id !== null) {
            $currentNode = $this->graphResolver->findNode($version, $run->current_node_id);
            $rendered = $this->nodeHandlers->for($this->nodeType($currentNode))->render(
                $version,
                $currentNode,
                $renderContext,
            );
        }

        return [
            'run' => [
                'id' => $run->id,
                'number' => $run->number,
                'number_formatted' => $run->formattedNumber(),
                'scenario_id' => $run->scenario_id,
                'scenario_name' => $run->scenario?->name,
                'scenario_version_id' => $run->scenario_version_id,
                'scenario_version_revision_id' => $run->scenario_version_revision_id,
                'scenario_version_name' => $run->version?->name,
                'scenario_version_created_at' => $run->version?->created_at?->toIso8601String(),
                'created_at' => $run->created_at?->toIso8601String(),
                'current_node_id' => $run->current_node_id,
                'status' => $run->status->value,
                'context' => $run->context ?? [],
                'current_node' => $currentNode,
                'rendered' => $rendered,
                'steps' => $run->steps->map(function (ScenarioRunStep $step) use ($version, $renderContext): array {
                    $stepRendered = null;
                    try {
                        $node = $this->graphResolver->findNode($version, $step->node_id);
                        $stepRendered = $this->nodeHandlers->for($this->nodeType($node))->render(
                            $version,
                            $node,
                            $renderContext,
                        );
                    } catch (\Throwable) {
                        // Node may not exist in current version
                    }

                    return [
                        'id' => $step->id,
                        'node_id' => $step->node_id,
                        'node_type' => $step->node_type->value,
                        'input' => $step->input,
                        'output' => $step->output,
                        'rendered' => $stepRendered,
                        'entered_at' => $step->entered_at?->toIso8601String(),
                        'exited_at' => $step->exited_at?->toIso8601String(),
                    ];
                })->values()->all(),
            ],
        ];
    }

    private function progress(ScenarioRun $run): ScenarioRun
    {
        $version = $this->runVersion($run);

        while ($this->isActive($run) && $run->current_node_id !== null) {
            $node = $this->graphResolver->findNode($version, $run->current_node_id);
            $handler = $this->nodeHandlers->for($this->nodeType($node));

            if (!$this->guardAgainstLoops($run, $this->nodeId($node))) {
                break;
            }

            if ($handler->isInteractive($node)) {
                $this->stepManager->ensureOpen($run, $node);

                if ($this->nodeType($node) === ScenarioNodeType::End->value) {
                    $this->completeRun($run, $this->nodeId($node), ['completed' => true]);
                }

                break;
            }

            $result = $handler->advance($run, $node);

            if ($result->runMutated) {
                $run = $this->hydrateRun($run);
                $version = $this->runVersion($run);

                continue;
            }

            // Узел запустил асинхронную работу и приостановил прогон на себе
            // (action-нода с wait_for_result ждёт завершения цепочки экшенов).
            if ($result->pause) {
                $this->stepManager->ensureOpen($this->hydrateRun($run), $node);

                break;
            }

            $run = $this->transition($run, $node, $result->nextNodeId, []);
            // Reload version: transition may have changed the scenario (e.g. via scenario_link).
            $version = $this->runVersion($run);
        }

        return $this->hydrateRun($run);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $input
     */
    private function transition(ScenarioRun $run, array $node, ?string $nextNodeId, array $input): ScenarioRun
    {
        $this->stepManager->closeOpen($run, $input, ['next_node_id' => $nextNodeId]);

        if ($nextNodeId === null) {
            return $this->completeRun($run, $this->nodeId($node));
        }

        $version = $this->runVersion($run);
        $nextNode = $this->graphResolver->findNode($version, $nextNodeId);
        $this->moveToNode($run, $this->nodeId($nextNode));

        return $this->progress($this->hydrateRun($run));
    }

    private function guardAgainstLoops(ScenarioRun $run, string $nodeId): bool
    {
        $context = $run->context ?? [];

        $rawPlayer = $context[RunContextKeys::PLAYER] ?? null;
        $player = is_array($rawPlayer) ? $rawPlayer : ['total_steps' => 0, 'visited' => []];

        $rawSteps = $player['total_steps'] ?? 0;
        $totalSteps = (is_int($rawSteps) ? $rawSteps : 0) + 1;

        $rawVisited = $player['visited'] ?? [];
        $visited = is_array($rawVisited) ? $rawVisited : [];
        $rawNodeVisits = $visited[$nodeId] ?? 0;
        $nodeVisits = (is_int($rawNodeVisits) ? $rawNodeVisits : 0) + 1;

        $updatedPlayer = [
            'total_steps' => $totalSteps,
            'visited' => [...$visited, $nodeId => $nodeVisits],
        ];

        if ($totalSteps > self::MAX_STEPS || $nodeVisits > self::MAX_VISITS_PER_NODE) {
            $run->forceFill([
                'status' => ScenarioRunStatus::Failed,
                'context' => [...$context, RunContextKeys::PLAYER => $updatedPlayer],
            ])->save();

            return false;
        }

        $run->forceFill([
            'context' => [...$context, RunContextKeys::PLAYER => $updatedPlayer],
        ])->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $schemaJson
     * @return array<string, mixed>
     */
    private function initializeContext(array $context, array $userData, array $schemaJson): array
    {
        $base = $userData !== [] ? ['user' => $userData] : [];

        return array_merge(
            $base,
            $context,
            [
                RunContextKeys::PLAYER => ['total_steps' => 0, 'visited' => []],
                RunContextKeys::VARIABLE_MAP => $this->buildVariableMap($schemaJson),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $schemaJson
     * @return array<string, array<string, mixed>>
     */
    private function buildVariableMap(array $schemaJson): array
    {
        $map = [];
        $rawBlocks = $schemaJson['blocks'] ?? ($schemaJson['nodes'] ?? []);
        /** @var array<mixed> $blocks */
        $blocks = is_array($rawBlocks) ? $rawBlocks : [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            /** @var array<string, mixed> $block */
            $blockType = isset($block['type']) && is_string($block['type']) ? $block['type'] : '';
            if ($blockType !== 'block') {
                continue;
            }

            $blockId = isset($block['id']) && is_string($block['id']) ? $block['id'] : '';
            $blockData = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];
            $rawFields = isset($blockData['fields']) && is_array($blockData['fields']) ? $blockData['fields'] : [];

            foreach ($rawFields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                /** @var array<string, mixed> $field */
                $varName = isset($field['varName']) && is_string($field['varName']) ? trim($field['varName']) : '';
                $name = isset($field['name']) && is_string($field['name']) ? trim($field['name']) : '';
                $fieldType = isset($field['type']) && is_string($field['type']) ? $field['type'] : 'input';

                if ($varName === '' || $name === '') {
                    continue;
                }

                /** @var array<string, mixed> $entry */
                $entry = [
                    '_block_id' => $blockId,
                    '_field_name' => $name,
                    '_field_type' => $fieldType,
                ];

                if ($fieldType === 'select') {
                    $rawOpts = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                    $entry['_options'] = array_values(array_filter($rawOpts, is_array(...)));
                } elseif (in_array($fieldType, ['date', 'datetime'], true)) {
                    $entry['_format'] = isset($field['format']) && is_string($field['format']) ? $field['format'] : '';
                } elseif (in_array($fieldType, ['directory_list', 'directory_table'], true)) {
                    $entry['_directory_id'] = isset($field['directoryId']) && is_string(
                        $field['directoryId']
                    ) ? $field['directoryId'] : '';
                    $entry['_version_id'] = isset($field['versionId']) && is_string(
                        $field['versionId']
                    ) ? $field['versionId'] : '';
                    $entry['_label_template'] = isset($field['labelTemplate']) && is_string(
                        $field['labelTemplate']
                    ) ? $field['labelTemplate'] : '';
                    $entry['_multiple'] = isset($field['multiple']) && (bool)$field['multiple'];
                }

                $map[$varName] = $entry; // последний блок перезаписывает
            }
        }

        return $map;
    }

    private function isActive(ScenarioRun $run): bool
    {
        return $run->status === ScenarioRunStatus::Active;
    }

    private function moveToNode(ScenarioRun $run, string $nodeId): void
    {
        $run->forceFill(['current_node_id' => $nodeId])->save();
    }

    /** @param  array<string, mixed>  $output */
    private function completeRun(ScenarioRun $run, string $nodeId, array $output = []): ScenarioRun
    {
        if ($output !== []) {
            $this->stepManager->closeOpen($run, [], $output);
        }

        $run->forceFill([
            'status' => ScenarioRunStatus::Completed,
            'current_node_id' => $nodeId,
        ])->save();

        return $this->hydrateRun($run);
    }

    private function hydrateRun(ScenarioRun $run): ScenarioRun
    {
        $run = $this->runs->hydrate($run);

        if ($run->revision !== null && $run->version !== null) {
            $run->version->setRelation('latestRevision', $run->revision);
        }

        return $run;
    }

    private function runVersion(ScenarioRun $run): ScenarioVersion
    {
        return $run->version ?? throw new \RuntimeException('Run version is not loaded.');
    }

    /** @param  array<string, mixed>  $node */
    private function nodeId(array $node): string
    {
        $id = $node['id'] ?? null;

        return is_string($id) ? $id : throw new \RuntimeException('Node has no id.');
    }

    /** @param  array<string, mixed>  $node */
    private function nodeType(array $node): string
    {
        $type = $node['type'] ?? null;

        return is_string($type) ? $type : throw new \RuntimeException('Node has no type.');
    }
}
