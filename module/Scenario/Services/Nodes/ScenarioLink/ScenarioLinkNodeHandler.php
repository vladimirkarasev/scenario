<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\ScenarioLink;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\DTO\ScenarioCallFrame;
use Module\Scenario\DTO\ScenarioCallStack;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Events\ScenarioLinkFollowed;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioRunStepRepository;
use Module\Scenario\Repositories\ScenarioVersionRepository;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Variables\ScenarioVariableMapBuilder;
use RuntimeException;

final readonly class ScenarioLinkNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private ScenarioRunStepRepository $steps,
        private ScenarioVariableMapBuilder $variableMapBuilder,
        private ScenarioVersionRepository $versions,
        private Dispatcher $events,
        private NodeDataReader $nodeData,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        return false;
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $data = $this->nodeData->data($node);
        $targetScenarioId = $this->nodeData->string($data, 'targetScenarioId');
        $targetVersionId = trim($this->nodeData->string($data, 'targetVersionId'));

        if (!Str::isUuid($targetScenarioId)) {
            throw new RuntimeException('Scenario link target scenario id must be a valid UUID.');
        }

        if ($targetVersionId === '') {
            $targetVersion = $this->versions->activeOrLatestForScenario($targetScenarioId);
        } else {
            if (!Str::isUuid($targetVersionId)) {
                throw new RuntimeException('Scenario link target version id must be a valid UUID.');
            }

            $targetVersion = $this->versions->findForScenario($targetVersionId, $targetScenarioId);
        }

        if ($targetVersion === null) {
            throw new RuntimeException('Scenario link target version not found.');
        }

        $targetRevision = $targetVersion->latestRevision()->first();

        if ($targetRevision === null) {
            throw new RuntimeException('Scenario link target version has no revision.');
        }

        $startNode = $this->graphResolver->findStartNode($targetVersion);
        $startNodeId = $this->nodeData->id($startNode);
        $nodeId = $this->nodeData->id($node);
        $nodeType = $this->nodeData->type($node);

        $parentVersion = $this->parentVersion($run);
        $returnNodeId = $parentVersion !== null
            ? $this->graphResolver->defaultNextNodeId($parentVersion, $nodeId)
            : null;

        $step = $this->steps->create($run, [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'entered_at' => now(),
            'exited_at' => now(),
            'output' => [
                'linked_scenario_id' => $targetScenarioId,
                'next_node_id' => $startNodeId,
            ],
        ]);

        $this->events->dispatch(new ScenarioLinkFollowed(
            $run,
            $node,
            $step,
            $targetScenarioId,
            $targetVersion->scenario?->name,
            $targetVersion->id,
            $startNodeId,
        ));

        $context = is_array($run->context) ? $run->context : [];
        $targetVariableMap = $this->variableMapBuilder->build($targetRevision->schema_json ?? []);
        $currentVariableMap = is_array($context[ScenarioContextKey::VariableMap->value] ?? null)
            ? $context[ScenarioContextKey::VariableMap->value]
            : [];
        $callStack = ScenarioCallStack::from($context[ScenarioContextKey::CallStack->value] ?? null)->push(
            new ScenarioCallFrame(
                versionId: $run->scenario_version_id,
                revisionId: $run->scenario_version_revision_id,
                returnNodeId: $returnNodeId,
            ),
        );

        $run->forceFill([
            'scenario_version_id' => $targetVersion->id,
            'scenario_version_revision_id' => $targetRevision->id,
            'current_node_id' => $startNodeId,
            'context' => [
                ...$context,
                ScenarioContextKey::CallStack->value => $callStack->toArray(),
                ScenarioContextKey::VariableMap->value => [...$currentVariableMap, ...$targetVariableMap],
            ],
        ])->save();

        return NodeAdvanceResult::mutated();
    }

    private function parentVersion(ScenarioRun $run): ?ScenarioVersion
    {
        $version = $run->version;

        if ($version === null) {
            return null;
        }

        if ($run->revision !== null) {
            $version->setRelation('latestRevision', $run->revision);
        }

        return $version;
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        return null;
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        return [
            'type' => $this->nodeData->type($node),
            'data' => $this->nodeData->data($node),
        ];
    }
}
