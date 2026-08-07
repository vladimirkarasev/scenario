<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Condition;

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Events\ScenarioConditionEvaluated;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\ConditionEvaluator;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\ScenarioGraphResolver;
use Module\Scenario\Services\VariableResolver;

final readonly class ConditionNodeHandler implements NodeHandlerInterface
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private ConditionEvaluator $conditionEvaluator,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        $data = $this->nodeData($node);

        return $this->strField($data, 'mode', 'manual') === 'manual'
            && trim($this->strField($data, 'value')) === '';
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $nodeData = $this->nodeData($node);

        if ($this->strField($nodeData, 'mode', 'manual') === 'manual') {
            $version = $this->runVersion($run);
            $targetNodeId = $this->conditionEvaluator->resolveEdgeTarget(
                $nodeData,
                $this->graphResolver->outgoingEdges($version, $this->nodeId($node)),
                $run->context ?? [],
            );

            if ($targetNodeId === null) {
                return NodeAdvanceResult::pause();
            }

            $label = $this->manualOptionLabel($version, $node, $targetNodeId);
            Event::dispatch(new ScenarioConditionEvaluated($run, $node, 'auto', $label, $targetNodeId));

            return NodeAdvanceResult::next($targetNodeId);
        }

        $targetNodeId = $this->conditionEvaluator->resolveTarget($nodeData, $run->context ?? []);

        Event::dispatch(new ScenarioConditionEvaluated($run, $node, 'auto', null, $targetNodeId));

        return NodeAdvanceResult::next($targetNodeId);
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        $nodeData = $this->nodeData($node);

        if ($this->strField($nodeData, 'mode', 'manual') === 'manual') {
            $version = $this->runVersion($run);
            $targetNodeId = $this->resolveManualTarget($version, $node, $data->selectedTargetNodeId);
            $label = $this->manualOptionLabel($version, $node, $targetNodeId);

            Event::dispatch(new ScenarioConditionEvaluated($run, $node, 'manual', $label, $targetNodeId));

            return $targetNodeId;
        }

        $targetNodeId = $this->conditionEvaluator->resolveTarget($nodeData, $run->context ?? []);

        Event::dispatch(new ScenarioConditionEvaluated($run, $node, 'auto', null, $targetNodeId));

        return $targetNodeId;
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        $data = $this->nodeData($node);
        $content = $data['content'] ?? null;

        $question = $this->strField($data, 'question')
            ?: $this->strField($data, 'title')
                ?: $this->strField($data, 'text');

        return [
            'type' => 'condition',
            'mode' => $this->strField($data, 'mode', 'manual'),
            'question' => $this->variableResolver->resolve($question, $context),
            'hideTitle' => $this->boolField($data, 'hideTitle', true),
            'content' => is_array($content) ? $this->variableResolver->resolve($content, $context) : null,
            'options' => $this->variableResolver->resolve($this->manualConditionOptions($version, $node), $context),
            'expression' => $data['expression'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function manualOptionLabel(ScenarioVersion $version, array $node, string $targetNodeId): ?string
    {
        foreach ($this->manualConditionOptions($version, $node) as $option) {
            if ($option['targetNodeId'] === $targetNodeId) {
                return $option['label'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function resolveManualTarget(ScenarioVersion $version, array $node, ?string $selectedTargetNodeId): string
    {
        $allowed = array_column($this->manualConditionOptions($version, $node), 'targetNodeId');

        if ($selectedTargetNodeId === null || !in_array($selectedTargetNodeId, $allowed, true)) {
            throw ValidationException::withMessages([
                'selected_target_node_id' => ['Selected manual condition target is invalid.'],
            ]);
        }

        return $selectedTargetNodeId;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<int, array{label: string, targetNodeId: string}>
     */
    private function manualConditionOptions(ScenarioVersion $version, array $node): array
    {
        $data = $this->nodeData($node);

        $rawOptions = $this->arrayField($data, 'options');
        $options = [];

        foreach ($rawOptions as $option) {
            if (!is_array($option)) {
                continue;
            }
            $targetNodeId = $option['targetNodeId'] ?? null;
            if (!is_string($targetNodeId) || $targetNodeId === '') {
                continue;
            }
            $options[] = [
                'label' => $this->strField($option, 'label', 'Continue'),
                'targetNodeId' => $targetNodeId,
            ];
        }

        if ($options !== []) {
            return $options;
        }

        $branches = [];
        foreach ($this->arrayField($data, 'conditionBranches') as $branch) {
            if (!is_array($branch)) {
                continue;
            }
            $branchId = $branch['id'] ?? null;
            if (is_string($branchId) && $branchId !== '') {
                $branches[$branchId] = $this->strField($branch, 'label', 'Continue');
            }
        }

        $result = [];

        foreach ($this->graphResolver->outgoingEdges($version, $this->nodeId($node)) as $edge) {
            $targetNodeId = $edge['target'] ?? null;
            if (!is_string($targetNodeId) || $targetNodeId === '') {
                continue;
            }

            $edgeLabel = $edge['label'] ?? null;
            $sourceHandle = $edge['sourceHandle'] ?? null;

            $label = is_string($edgeLabel) && $edgeLabel !== ''
                ? $edgeLabel
                : (is_string($sourceHandle) ? ($branches[$sourceHandle] ?? 'Continue') : 'Continue');

            $result[] = [
                'label' => $label,
                'targetNodeId' => $targetNodeId,
            ];
        }

        return $result;
    }
}
