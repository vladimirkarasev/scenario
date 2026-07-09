<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\ScenarioLink;

use Illuminate\Support\Str;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioRunStepRepository;
use Module\Scenario\Repositories\ScenarioVersionRepository;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\RunContextKeys;
use Module\Scenario\Services\ScenarioGraphResolver;
use Module\Scenario\Services\ScenarioVariableMapBuilder;
use RuntimeException;

final readonly class ScenarioLinkNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private ScenarioRunStepRepository $steps,
        private ScenarioVariableMapBuilder $variableMapBuilder,
        private ScenarioVersionRepository $versions,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        return false;
    }

    /**
     * Входит в связный сценарий как в подпрограмму: запоминает в стеке вызовов
     * точку возврата в родителя и переключает исполнение на стартовый узел цели.
     * Идентичность прогона (scenario_id) остаётся за исходным сценарием — в плеере
     * это выглядит как один сквозной сценарий.
     */
    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $data = $this->nodeData($node);
        $targetScenarioId = $this->strField($data, 'targetScenarioId');
        $targetVersionId = trim($this->strField($data, 'targetVersionId'));

        if (!Str::isUuid($targetScenarioId)) {
            throw new RuntimeException('Scenario link target scenario id must be a valid UUID.');
        }

        if ($targetVersionId === '') {
            // Версия не закреплена — берём последнюю активную (иначе последнюю) версию сценария.
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
        $startNodeId = $this->nodeId($startNode);
        $nodeId = $this->nodeId($node);
        $nodeType = $this->nodeType($node);

        // Узел возврата в родителе — следующий за блоком-переходом. Если его нет,
        // после связного сценария родительская ветка считается завершённой.
        $parentVersion = $this->parentVersion($run);
        $returnNodeId = $parentVersion !== null
            ? $this->graphResolver->defaultNextNodeId($parentVersion, $nodeId)
            : null;

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

        $context = is_array($run->context) ? $run->context : [];
        // Ревизия гарантированно существует: findStartNode выше уже её требует.
        $targetVariableMap = $this->variableMapBuilder->build($targetRevision->schema_json ?? []);
        $currentVariableMap = is_array($context[RunContextKeys::VARIABLE_MAP] ?? null)
            ? $context[RunContextKeys::VARIABLE_MAP]
            : [];
        $callStack = is_array($context[RunContextKeys::CALL_STACK] ?? null)
            ? $context[RunContextKeys::CALL_STACK]
            : [];

        // Кладём кадр родителя в стек: куда вернуться, когда цель дойдёт до «Конца».
        $callStack[] = [
            'version_id' => $run->scenario_version_id,
            'revision_id' => $run->scenario_version_revision_id,
            'return_node_id' => $returnNodeId,
        ];

        $run->forceFill([
            'scenario_version_id' => $targetVersion->id,
            'scenario_version_revision_id' => $targetRevision->id,
            'current_node_id' => $startNodeId,
            'context' => [
                ...$context,
                RunContextKeys::CALL_STACK => $callStack,
                RunContextKeys::VARIABLE_MAP => [...$currentVariableMap, ...$targetVariableMap],
            ],
        ])->save();

        return NodeAdvanceResult::mutated();
    }

    /** Текущая исполняемая (родительская) версия прогона с подгруженной ревизией. */
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
            'type' => $this->nodeType($node),
            'data' => $this->nodeData($node),
        ];
    }
}
