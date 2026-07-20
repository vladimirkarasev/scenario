<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\DTO\ScenarioStartData;
use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioRepository;
use Module\Scenario\Repositories\ScenarioRunRepository;
use Module\Scenario\Repositories\ScenarioRunStepRepository;
use Module\Scenario\Repositories\ScenarioVersionRepository;
use Module\Scenario\Repositories\ScenarioVersionRevisionRepository;
use Module\Scenario\Services\Nodes\NodeContextKeys;
use Module\Scenario\Services\Nodes\NodeHandlerRegistry;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\Nodes\RetryableNodeHandler;

final readonly class ScenarioPlayerService
{
    use NodeHelpers;

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
        private ScenarioVersionRevisionRepository $revisions,
        private VariableResolver $variableResolver,
        private ScenarioVariableMapBuilder $variableMapBuilder,
    ) {}

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
    public function start(ScenarioStartData $data, ?string $projectId = null): string
    {
        $version = match (true) {
            $data->versionId !== null => $projectId !== null
                ? $this->versions->getByIdInProject($data->versionId, $projectId)
                : $this->versions->getById($data->versionId),
            $data->scenarioId !== null => $this->versions->resolveForScenario(
                $projectId !== null
                    ? $this->scenarios->getByIdInProject($data->scenarioId, $projectId)
                    : $this->scenarios->getById($data->scenarioId),
                null,
            ),
            default => $this->versions->resolveForScenario(
                $projectId !== null
                    ? $this->scenarios->getByAliasInProject((string) $data->alias, $projectId)
                    : $this->scenarios->getByAlias((string) $data->alias),
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
            $projectId,
        )->id;
    }

    /** Создать прогон по явным ID сценария и/или версии, автопродвинуть до первого интерактивного узла. */
    public function createRun(ScenarioRunData $data, ?string $projectId = null): ScenarioRun
    {
        if ($data->scenarioId !== null) {
            $scenario = $projectId !== null
                ? $this->scenarios->getByIdInProject($data->scenarioId, $projectId)
                : $this->scenarios->getById($data->scenarioId);
            $version = $this->versions->resolveForScenario($scenario, $data->scenarioVersionId);
        } else {
            $versionId = (string) $data->scenarioVersionId;
            $version = $projectId !== null
                ? $this->versions->getByIdInProject($versionId, $projectId)
                : $this->versions->getById($versionId);
            $scenario = $projectId !== null
                ? $this->scenarios->getByIdInProject($version->scenario_id, $projectId)
                : $this->scenarios->getById($version->scenario_id);
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

        if (! $this->isActive($run)) {
            return $run;
        }

        $node = $this->graphResolver->findNode($this->runVersion($run), (string) $run->current_node_id);
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

        if (! $this->isActive($run) || $run->current_node_id === null) {
            return $run;
        }

        $node = $this->graphResolver->findNode($this->runVersion($run), (string) $run->current_node_id);
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

        if (! $this->isActive($run) || (string) $run->current_node_id !== $nodeId) {
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

        // Шаг-цель ищем ДО разрешения узла: его снапшот хранит версию/ревизию/стек,
        // нужные чтобы узел связного сценария находился в правильной версии.
        $targetStep = $this->steps->latestForNode($run, $data->nodeId);
        $context = $run->context ?? [];

        if ($targetStep !== null) {
            // Восстанавливаем состояние исполнения на момент шага (связные сценарии).
            if (is_string($targetStep->scenario_version_id)) {
                $run->forceFill([
                    'scenario_version_id' => $targetStep->scenario_version_id,
                    'scenario_version_revision_id' => $targetStep->scenario_version_revision_id,
                ])->save();
                $run = $this->hydrateRun($run);
                $context = $run->context ?? [];
                $context[RunContextKeys::CALL_STACK] = is_array($targetStep->call_stack)
                    ? $targetStep->call_stack
                    : [];
            }

            $this->steps->trimAfter($run, (int) $targetStep->id);
            $this->steps->cancel($targetStep);
        } else {
            $this->stepManager->closeOpen($run, [], []);
        }

        $node = $this->graphResolver->findNode($this->runVersion($run), $data->nodeId);

        $context[RunContextKeys::PLAYER] = ['total_steps' => 0, 'visited' => []];

        unset($context[NodeContextKeys::ACTION_RUNS], $context[NodeContextKeys::ACTION_STAGES]);

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
        $call = is_array($run->context['call'] ?? null) ? $run->context['call'] : [];
        $renderContext = [
            ...($run->context ?? []),
            'run' => [
                'id' => $run->id,
                'number' => $run->number,
                'number_formatted' => $run->formattedNumber(),
                'created_at' => $run->created_at?->format('d.m.Y H:i'),
                'completed_at' => $isFinished ? $run->updated_at?->format('d.m.Y H:i') : null,
            ],
            'operator' => [
                'login' => $operator?->login,
                'name' => $operator?->name,
                'fio' => $operator?->fio,
            ],
            'project' => [
                'name' => $operator?->project?->name,
                'id' => $operator?->project?->id,
            ],
            'call' => [
                'incoming_phone' => $call['incoming_phone'] ?? null,
                'outgoing_phone' => $call['outgoing_phone'] ?? null,
                'internal_phone' => $call['internal_phone'] ?? null,
                'id' => $call['id'] ?? null,
            ],
        ];

        if ($run->current_node_id !== null) {
            $currentNode = $this->graphResolver->findNode($version, $run->current_node_id);
            $rendered = $this->nodeHandlers->for($this->nodeType($currentNode))->render(
                $version,
                $currentNode,
                $renderContext,
            );
        }

        /** @var array<string, ScenarioVersion> $versionCache */
        $versionCache = [];

        // Корневой (верхнеуровневый) сценарий прогона — для разделителей таймлайна,
        // когда прогон сразу уходит в связный сценарий. Дно стека вызовов = корень.
        $callStack = is_array($run->context[RunContextKeys::CALL_STACK] ?? null)
            ? $run->context[RunContextKeys::CALL_STACK]
            : [];
        $rootFrame = $callStack[0] ?? null;
        $rootVersionId = is_array($rootFrame) && is_string($rootFrame['version_id'] ?? null)
            ? $rootFrame['version_id']
            : $run->scenario_version_id;
        $rootVersion = $this->resolveVersionById($rootVersionId, $version, $versionCache);

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
                // Имя сценария, чья версия исполняется сейчас (для связных — целевого).
                'current_scenario_name' => $version->scenario?->name,
                // Корневой сценарий/версия — стартовая точка таймлайна (для разделителей).
                'root_scenario_version_id' => $rootVersionId,
                'root_scenario_name' => $rootVersion->scenario?->name,
                'root_scenario_version_name' => $rootVersion->name,
                'scenario_version_created_at' => $run->version?->created_at?->toIso8601String(),
                'created_at' => $run->created_at?->toIso8601String(),
                'current_node_id' => $run->current_node_id,
                'status' => $run->status->value,
                // Шапка плеера: оператор (кто ведёт опрос).
                'operator' => $run->operator ? [
                    'id' => $run->operator->id,
                    'name' => $run->operator->name ?? $run->operator->login,
                    'fio' => $run->operator->fio,
                    'login' => $run->operator->login,
                ] : null,
                // Клиент — опциональные данные, переданные при создании опроса
                // (context.user): ФИО и телефон. Может отсутствовать.
                'client' => $this->clientPayload($run),
                'context' => $run->context ?? [],
                'current_node' => $currentNode,
                'rendered' => $rendered,
                'steps' => $run->steps->map(function (ScenarioRunStep $step) use ($version, $renderContext, &$versionCache): array {
                    $stepRendered = null;
                    // Шаг рендерится против своей версии (связные сценарии: шаги из разных версий).
                    $stepVersion = $this->resolveVersionById($step->scenario_version_id, $version, $versionCache);
                    try {
                        $node = $this->graphResolver->findNode($stepVersion, $step->node_id);
                        $stepRendered = $this->nodeHandlers->for($this->nodeType($node))->render(
                            $stepVersion,
                            $node,
                            $renderContext,
                        );
                    } catch (\Throwable) {
                        // Node may not exist in resolved version
                    }

                    return [
                        'id' => $step->id,
                        'node_id' => $step->node_id,
                        'node_type' => $step->node_type->value,
                        'scenario_version_id' => $step->scenario_version_id,
                        'scenario_name' => $stepVersion->scenario?->name,
                        'scenario_version_name' => $stepVersion->name,
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

            if (! $this->guardAgainstLoops($run, $this->nodeId($node))) {
                break;
            }

            // Связный сценарий: «Конец» внутри подпрограммы возвращает прогон
            // в родителя по стеку вызовов вместо завершения всего опроса.
            if ($this->nodeType($node) === ScenarioNodeType::End->value) {
                $frame = $this->peekCallFrame($run);

                if ($frame !== null && is_string($frame['return_node_id'] ?? null)) {
                    $run = $this->returnToParent($run, $frame);
                    $version = $this->runVersion($run);

                    continue;
                }

                $this->stepManager->ensureOpen($run, $node);
                $this->completeRun($run, $this->nodeId($node), ['completed' => true]);

                break;
            }

            if ($handler->isInteractive($node)) {
                $this->stepManager->ensureOpen($run, $node);

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
     * @param array<string, mixed> $node
     * @param array<string, mixed> $input
     */
    private function transition(ScenarioRun $run, array $node, ?string $nextNodeId, array $input): ScenarioRun
    {
        $this->stepManager->closeOpen($run, $input, ['next_node_id' => $nextNodeId]);

        if ($nextNodeId === null) {
            // Тупиковая ветка внутри связного сценария — возвращаемся в родителя,
            // иначе завершаем весь опрос.
            $frame = $this->peekCallFrame($run);

            if ($frame !== null && is_string($frame['return_node_id'] ?? null)) {
                return $this->progress($this->returnToParent($run, $frame));
            }

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
     * @param  array<string, mixed> $context
     * @param  array<string, mixed> $userData
     * @param  array<string, mixed> $schemaJson
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
                RunContextKeys::VARIABLE_MAP => $this->variableMapBuilder->build($schemaJson),
            ],
        );
    }

    private function isActive(ScenarioRun $run): bool
    {
        return $run->status === ScenarioRunStatus::Active;
    }

    private function moveToNode(ScenarioRun $run, string $nodeId): void
    {
        $run->forceFill(['current_node_id' => $nodeId])->save();
    }

    /**
     * Верхний кадр стека вызовов связных сценариев без снятия со стека.
     *
     * @return array<string, mixed>|null
     */
    private function peekCallFrame(ScenarioRun $run): ?array
    {
        $context = is_array($run->context) ? $run->context : [];
        $stack = is_array($context[RunContextKeys::CALL_STACK] ?? null)
            ? $context[RunContextKeys::CALL_STACK]
            : [];
        $frame = end($stack);

        if (! is_array($frame)) {
            return null;
        }

        /** @var array<string, mixed> $frame */
        return $frame;
    }

    /**
     * Снять кадр со стека вызовов, восстановить версию родителя и перейти на узел
     * возврата. Прогон остаётся активным — прогресс продолжится в родителе.
     *
     * @param  array<string, mixed>  $frame
     */
    private function returnToParent(ScenarioRun $run, array $frame): ScenarioRun
    {
        $context = is_array($run->context) ? $run->context : [];
        $stack = is_array($context[RunContextKeys::CALL_STACK] ?? null)
            ? $context[RunContextKeys::CALL_STACK]
            : [];
        array_pop($stack);
        $context[RunContextKeys::CALL_STACK] = $stack;

        $returnNodeId = $frame['return_node_id'];

        $run->forceFill([
            'scenario_version_id' => $frame['version_id'] ?? null,
            'scenario_version_revision_id' => $frame['revision_id'] ?? null,
            'current_node_id' => is_string($returnNodeId) ? $returnNodeId : null,
            'context' => $context,
        ])->save();

        return $this->hydrateRun($run);
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

    /**
     * Данные клиента из context.user (передаются опционально при создании опроса):
     * ФИО и телефон. null — если клиент не передан.
     *
     * @return array{fio: string|null, phone: string|null}|null
     */
    private function clientPayload(ScenarioRun $run): ?array
    {
        $context = is_array($run->context) ? $run->context : [];
        $user = is_array($context['user'] ?? null) ? $context['user'] : [];

        $fio = is_string($user['fio'] ?? null) && $user['fio'] !== ''
            ? $user['fio']
            : (is_string($user['name'] ?? null) && $user['name'] !== '' ? $user['name'] : null);
        $phone = is_string($user['phone'] ?? null) && $user['phone'] !== '' ? $user['phone'] : null;

        if ($fio === null && $phone === null) {
            return null;
        }

        return ['fio' => $fio, 'phone' => $phone];
    }

    /**
     * Версия по id с подгруженной последней ревизией (с кэшем в пределах запроса).
     * Нужна для рендера шагов связных сценариев: шаги одного прогона относятся к
     * разным версиям, и каждый рендерится против своей.
     *
     * @param  array<string, ScenarioVersion>  $cache
     */
    private function resolveVersionById(?string $versionId, ScenarioVersion $fallback, array &$cache): ScenarioVersion
    {
        if ($versionId === null || $versionId === $fallback->id) {
            return $fallback;
        }

        if (array_key_exists($versionId, $cache)) {
            return $cache[$versionId];
        }

        $version = ScenarioVersion::query()->find($versionId);

        if ($version !== null) {
            $version->setRelation('latestRevision', $this->revisions->getLastRevision($version));
        }

        return $cache[$versionId] = $version ?? $fallback;
    }

}
