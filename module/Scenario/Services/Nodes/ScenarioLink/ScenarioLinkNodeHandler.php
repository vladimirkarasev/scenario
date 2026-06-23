<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\ScenarioLink;

use Illuminate\Support\Str;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioRunStepRepository;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\ScenarioGraphResolver;
use RuntimeException;

final readonly class ScenarioLinkNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private ScenarioRunStepRepository $steps,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        return false;
    }

    /** Переключает прогон на целевой сценарий и сигнализирует о мутации. */
    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $data = $this->nodeData($node);
        $targetScenarioId = $this->strField($data, 'targetScenarioId');
        $targetVersionId = $this->strField($data, 'targetVersionId');

        if (!Str::isUuid($targetScenarioId)) {
            throw new RuntimeException('Scenario link target scenario id must be a valid UUID.');
        }

        if (!Str::isUuid($targetVersionId)) {
            throw new RuntimeException('Scenario link target version id must be a valid UUID.');
        }

        $targetVersion = ScenarioVersion::query()
            ->where('id', $targetVersionId)
            ->where('scenario_id', $targetScenarioId)
            ->first();

        if ($targetVersion === null) {
            throw new RuntimeException('Scenario link target version not found.');
        }

        $targetRevision = $targetVersion->latestRevision()->first();
        $startNode = $this->graphResolver->findStartNode($targetVersion);
        $startNodeId = $this->nodeId($startNode);
        $nodeId = $this->nodeId($node);
        $nodeType = $this->nodeType($node);

        $this->steps->create($run, [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'entered_at' => now(),
            'exited_at' => now(),
            'output' => [
                'linked_scenario_id' => $targetScenarioId,
                'next_node_id' => $startNodeId,
            ],
        ]);

        $run->forceFill([
            'scenario_id' => $targetVersion->scenario_id,
            'scenario_version_id' => $targetVersion->id,
            'scenario_version_revision_id' => $targetRevision?->id,
            'current_node_id' => $startNodeId,
        ])->save();

        return NodeAdvanceResult::mutated();
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        return null;
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        return [
            'type' => $this->nodeType($node),
            'data' => $this->nodeData($node),
        ];
    }
}
