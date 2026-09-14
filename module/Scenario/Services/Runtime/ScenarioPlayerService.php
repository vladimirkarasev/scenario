<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use Illuminate\Contracts\Events\Dispatcher;
use Module\Scenario\DTO\ScenarioCallFrame;
use Module\Scenario\DTO\ScenarioCallStack;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioRunData;
use Module\Scenario\DTO\ScenarioRunJumpData;
use Module\Scenario\DTO\ScenarioStartData;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Enums\ScenarioNodeType;
use Module\Scenario\Enums\ScenarioRunStatus;
use Module\Scenario\Events\ScenarioRunCompleted;
use Module\Scenario\Events\ScenarioRunRewound;
use Module\Scenario\Events\ScenarioRunStarted;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Repositories\ScenarioRepository;
use Module\Scenario\Repositories\ScenarioRunRepository;
use Module\Scenario\Repositories\ScenarioRunStepRepository;
use Module\Scenario\Repositories\ScenarioVersionRepository;
use Module\Scenario\Repositories\ScenarioVersionRevisionRepository;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Nodes\NodeHandlerRegistry;
use Module\Scenario\Services\Nodes\RetryableNodeHandler;
use Module\Scenario\Services\Variables\ScenarioVariableMapBuilder;

final readonly class ScenarioPlayerService
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private NodeHandlerRegistry $nodeHandlers,
        private ScenarioRunStepManager $stepManager,
        private ScenarioRepository $scenarios,
        private ScenarioVersionRepository $versions,
        private ScenarioRunRepository $runs,
        private ScenarioRunStepRepository $steps,
        private ScenarioVersionRevisionRepository $revisions,
        private ScenarioVariableMapBuilder $variableMapBuilder,
        private ScenarioRunLoopGuard $loopGuard,
        private ScenarioRunPayloadBuilder $payloadBuilder,
        private Dispatcher $events,
        private NodeDataReader $nodeData,
        private ScenarioRunVersionResolver $runVersions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function surveyData(ScenarioRun $run): array
    {
        return $this->payloadBuilder->surveyData($run);
    }

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
            'current_node_id' => $this->nodeData->id($startNode),
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

        $run = $this->hydrateRun($this->runs->create($attributes));

        $this->events->dispatch(new ScenarioRunStarted($run));

        return $this->progress($run);
    }

    public function getRun(ScenarioRun $run): ScenarioRun
    {
        return $this->progress($this->hydrateRun($run));
    }

    public function continueRun(ScenarioRun $run, ScenarioRunContinueData $data): ScenarioRun
    {
        $run = $this->hydrateRun($run);

        if (! $this->isActive($run)) {
            return $run;
        }

        $node = $this->graphResolver->findNode($this->runVersions->resolve($run), (string) $run->current_node_id);
        $nextNodeId = $this->nodeHandlers->for($this->nodeData->type($node))->continueFrom($run, $node, $data);

        return $this->transition($run, $node, $nextNodeId, $data->input);
    }

    public function retryActionNode(ScenarioRun $run): ScenarioRun
    {
        $run = $this->hydrateRun($run);

        if (! $this->isActive($run) || $run->current_node_id === null) {
            return $run;
        }

        $node = $this->graphResolver->findNode($this->runVersions->resolve($run), (string) $run->current_node_id);
        $handler = $this->nodeHandlers->for($this->nodeData->type($node));

        if ($handler instanceof RetryableNodeHandler) {
            $handler->retry($run, $node);
        }

        return $this->hydrateRun($run);
    }

    public function resumeFromActionNode(ScenarioRun $run, string $nodeId): ScenarioRun
    {
        $run = $this->hydrateRun($run);

        if (! $this->isActive($run) || (string) $run->current_node_id !== $nodeId) {
            return $run;
        }

        $version = $this->runVersions->resolve($run);
        $node = $this->graphResolver->findNode($version, $nodeId);
        $nextNodeId = $this->graphResolver->defaultNextNodeId($version, $nodeId);

        return $this->transition($run, $node, $nextNodeId, []);
    }

    public function jumpRun(ScenarioRun $run, ScenarioRunJumpData $data): ScenarioRun
    {
        $run = $this->hydrateRun($run);

        $targetStep = $this->steps->latestForNode($run, $data->nodeId);
        $context = $run->context ?? [];

        if ($targetStep !== null) {
            if (is_string($targetStep->scenario_version_id)) {
                $run->forceFill([
                    'scenario_version_id' => $targetStep->scenario_version_id,
                    'scenario_version_revision_id' => $targetStep->scenario_version_revision_id,
                ])->save();
                $run = $this->hydrateRun($run);
                $context = $run->context ?? [];
                $context[ScenarioContextKey::CallStack->value] = is_array($targetStep->call_stack)
                    ? $targetStep->call_stack
                    : [];
            }

            $this->steps->trimAfter($run, (int) $targetStep->id);
            $this->steps->cancel($targetStep);
        } else {
            $this->stepManager->closeOpen($run, [], []);
        }

        $node = $this->graphResolver->findNode($this->runVersions->resolve($run), $data->nodeId);

        if ($targetStep !== null) {
            $this->events->dispatch(new ScenarioRunRewound($run, $node));
        }

        $context[ScenarioContextKey::Player->value] = ['total_steps' => 0, 'visited' => []];

        unset(
            $context[ScenarioContextKey::ActionRuns->value],
            $context[ScenarioContextKey::ActionStages->value],
        );

        $run->forceFill([
            'status' => ScenarioRunStatus::Active,
            'current_node_id' => $this->nodeData->id($node),
            'context' => $context,
        ])->save();

        return $this->progress($this->hydrateRun($run));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ScenarioRun $run): array
    {
        return $this->payloadBuilder->build($run);
    }

    private function progress(ScenarioRun $run): ScenarioRun
    {
        $version = $this->runVersions->resolve($run);

        while ($this->isActive($run) && $run->current_node_id !== null) {
            $node = $this->graphResolver->findNode($version, $run->current_node_id);
            $handler = $this->nodeHandlers->for($this->nodeData->type($node));

            if (! $this->loopGuard->allows($run, $this->nodeData->id($node))) {
                break;
            }

            if ($this->nodeData->type($node) === ScenarioNodeType::End->value) {
                $frame = $this->peekCallFrame($run);

                if ($frame?->returnNodeId !== null) {
                    $run = $this->returnToParent($run, $frame);
                    $version = $this->runVersions->resolve($run);

                    continue;
                }

                $this->stepManager->ensureOpen($run, $node);
                $this->completeRun($run, $this->nodeData->id($node), ['completed' => true]);

                break;
            }

            if ($handler->isInteractive($node)) {
                $this->stepManager->ensureOpen($run, $node);

                break;
            }

            $result = $handler->advance($run, $node);

            if ($result->runMutated) {
                $run = $this->hydrateRun($run);
                $version = $this->runVersions->resolve($run);

                continue;
            }

            if ($result->pause) {
                $this->stepManager->ensureOpen($this->hydrateRun($run), $node);

                break;
            }

            $run = $this->transition($run, $node, $result->nextNodeId, []);
            $version = $this->runVersions->resolve($run);
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
            $frame = $this->peekCallFrame($run);

            if ($frame?->returnNodeId !== null) {
                return $this->progress($this->returnToParent($run, $frame));
            }

            return $this->completeRun($run, $this->nodeData->id($node));
        }

        $version = $this->runVersions->resolve($run);
        $nextNode = $this->graphResolver->findNode($version, $nextNodeId);
        $this->moveToNode($run, $this->nodeData->id($nextNode));

        return $this->progress($this->hydrateRun($run));
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
                ScenarioContextKey::Player->value => ['total_steps' => 0, 'visited' => []],
                ScenarioContextKey::VariableMap->value => $this->variableMapBuilder->build($schemaJson),
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

    private function peekCallFrame(ScenarioRun $run): ?ScenarioCallFrame
    {
        $context = is_array($run->context) ? $run->context : [];
        return ScenarioCallStack::from($context[ScenarioContextKey::CallStack->value] ?? null)->last();
    }

    private function returnToParent(ScenarioRun $run, ScenarioCallFrame $frame): ScenarioRun
    {
        $context = is_array($run->context) ? $run->context : [];
        $stack = ScenarioCallStack::from($context[ScenarioContextKey::CallStack->value] ?? null)->pop();
        $context[ScenarioContextKey::CallStack->value] = $stack->toArray();

        $run->forceFill([
            'scenario_version_id' => $frame->versionId,
            'scenario_version_revision_id' => $frame->revisionId,
            'current_node_id' => $frame->returnNodeId,
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

        $run = $this->hydrateRun($run);

        $this->events->dispatch(new ScenarioRunCompleted($run));

        return $run;
    }

    private function hydrateRun(ScenarioRun $run): ScenarioRun
    {
        $run = $this->runs->hydrate($run);

        if ($run->revision !== null && $run->version !== null) {
            $run->version->setRelation('latestRevision', $run->revision);
        }

        return $run;
    }

}
