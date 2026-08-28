<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Condition;

use App\Services\Expression\Functions\IsElseFunction;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Validation\ValidationException;
use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Events\ScenarioConditionEvaluated;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Runtime\ScenarioRunVersionResolver;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class ConditionNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private ConditionEvaluator $conditionEvaluator,
        private Dispatcher $events,
        private NodeDataReader $nodeData,
        private ScenarioRunVersionResolver $runVersions,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        $data = $this->nodeData->data($node);

        return $this->nodeData->string($data, 'mode', 'manual') === 'manual'
            && trim($this->nodeData->string($data, 'value')) === '';
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $nodeData = $this->nodeData->data($node);

        if ($this->nodeData->string($nodeData, 'mode', 'manual') === 'manual') {
            $version = $this->runVersions->resolve($run);
            $targetNodeId = $this->conditionEvaluator->resolveEdgeTarget(
                $nodeData,
                $this->graphResolver->outgoingEdges($version, $this->nodeData->id($node)),
                $run->context ?? [],
            );

            if ($targetNodeId === null) {
                return NodeAdvanceResult::pause();
            }

            $label = $this->manualOptionLabel($version, $node, $targetNodeId, $run->context ?? []);
            $this->events->dispatch(new ScenarioConditionEvaluated($run, $node, 'auto', $label, $targetNodeId));

            return NodeAdvanceResult::next($targetNodeId);
        }

        $targetNodeId = $this->conditionEvaluator->resolveTarget($nodeData, $run->context ?? []);

        $this->events->dispatch(new ScenarioConditionEvaluated($run, $node, 'auto', null, $targetNodeId));

        return NodeAdvanceResult::next($targetNodeId);
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        $nodeData = $this->nodeData->data($node);

        if ($this->nodeData->string($nodeData, 'mode', 'manual') === 'manual') {
            $version = $this->runVersions->resolve($run);
            $targetNodeId = $this->resolveManualTarget(
                $version,
                $node,
                $data->selectedTargetNodeId,
                $run->context ?? [],
            );
            $label = $this->manualOptionLabel($version, $node, $targetNodeId, $run->context ?? []);

            $this->events->dispatch(new ScenarioConditionEvaluated($run, $node, 'manual', $label, $targetNodeId));

            return $targetNodeId;
        }

        $targetNodeId = $this->conditionEvaluator->resolveTarget($nodeData, $run->context ?? []);

        $this->events->dispatch(new ScenarioConditionEvaluated($run, $node, 'auto', null, $targetNodeId));

        return $targetNodeId;
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        $data = $this->nodeData->data($node);
        $content = $data['content'] ?? null;

        $question = $this->nodeData->string($data, 'question')
            ?: $this->nodeData->string($data, 'title')
                ?: $this->nodeData->string($data, 'text');

        return [
            'type' => 'condition',
            'mode' => $this->nodeData->string($data, 'mode', 'manual'),
            'question' => $this->variableResolver->resolve($question, $context),
            'hideTitle' => $this->nodeData->boolean($data, 'hideTitle', true),
            'content' => is_array($content) ? $this->variableResolver->resolve($content, $context) : null,
            'options' => $this->variableResolver->resolve(
                $this->manualConditionOptions($version, $node, $context),
                $context,
            ),
            'expression' => $data['expression'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     */
    private function manualOptionLabel(
        ScenarioVersion $version,
        array $node,
        string $targetNodeId,
        array $context,
    ): ?string
    {
        foreach ($this->manualConditionOptions($version, $node, $context) as $option) {
            if ($option['targetNodeId'] === $targetNodeId) {
                return $option['label'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     */
    private function resolveManualTarget(
        ScenarioVersion $version,
        array $node,
        ?string $selectedTargetNodeId,
        array $context,
    ): string
    {
        $allowed = array_filter(
            array_column($this->manualConditionOptions($version, $node, $context), 'targetNodeId'),
            is_string(...),
        );

        if ($selectedTargetNodeId === null || !in_array($selectedTargetNodeId, $allowed, true)) {
            throw ValidationException::withMessages([
                'selected_target_node_id' => ['Selected manual condition target is invalid.'],
            ]);
        }

        return $selectedTargetNodeId;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array<int, array{label: string, icon: ?string, targetNodeId: ?string, url: ?string, width: string}>
     */
    private function manualConditionOptions(ScenarioVersion $version, array $node, array $context = []): array
    {
        $data = $this->nodeData->data($node);

        $rawOptions = $this->nodeData->array($data, 'options');
        usort($rawOptions, fn(mixed $left, mixed $right): int => $this->optionPriority($left) <=> $this->optionPriority($right));
        $targetsByOption = [];

        foreach ($this->graphResolver->outgoingEdges($version, $this->nodeData->id($node)) as $edge) {
            $sourceHandle = $edge['sourceHandle'] ?? null;
            $targetNodeId = $edge['target'] ?? null;

            if (is_string($sourceHandle) && is_string($targetNodeId) && $targetNodeId !== '') {
                $targetsByOption[$sourceHandle] = $targetNodeId;
            }
        }

        $options = [];
        $elseOptions = [];

        foreach ($rawOptions as $rawOption) {
            $option = $this->stringKeyedArray($rawOption);
            if ($option === null) {
                continue;
            }
            $optionId = $option['id'] ?? null;
            $targetNodeId = $option['targetNodeId']
                ?? (is_string($optionId) ? ($targetsByOption[$optionId] ?? null) : null);
            $url = $this->externalUrl($option['url'] ?? null);
            if ((!is_string($targetNodeId) || $targetNodeId === '') && $url === null) {
                continue;
            }
            if (!$this->isOptionVisible($option, $context)) {
                continue;
            }
            $resolvedOption = [
                'label' => $this->nodeData->string($option, 'label', 'Continue'),
                'icon' => $this->nullableString($option['icon'] ?? null),
                'targetNodeId' => is_string($targetNodeId) && $targetNodeId !== '' ? $targetNodeId : null,
                'url' => $url,
                'width' => $this->nodeData->string($option, 'width') === 'half' ? 'half' : 'full',
            ];

            if ($this->isElseOption($option)) {
                $elseOptions[] = $resolvedOption;

                continue;
            }

            $options[] = $resolvedOption;
        }

        if ($options !== []) {
            return $options;
        }

        if ($elseOptions !== []) {
            return $elseOptions;
        }

        if ($rawOptions !== []) {
            return [];
        }

        $edgeOptions = [];

        foreach ($this->graphResolver->outgoingEdges($version, $this->nodeData->id($node)) as $edge) {
            $targetNodeId = $edge['target'] ?? null;
            if (!is_string($targetNodeId) || $targetNodeId === '') {
                continue;
            }

            $edgeLabel = $edge['label'] ?? null;
            $edgeOptions[] = [
                'label' => is_string($edgeLabel) && $edgeLabel !== '' ? $edgeLabel : 'Continue',
                'icon' => null,
                'targetNodeId' => $targetNodeId,
                'url' => null,
                'width' => 'full',
            ];
        }

        return $edgeOptions;
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $context
     */
    private function isOptionVisible(array $option, array $context): bool
    {
        $condition = $this->nodeData->string($option, 'condition');

        return $condition !== '' && $this->variableResolver->resolveValue($condition, $context) === true;
    }

    /** @param  array<string, mixed>  $option */
    private function isElseOption(array $option): bool
    {
        return IsElseFunction::matches($this->nodeData->string($option, 'condition'));
    }

    private function optionPriority(mixed $option): int
    {
        $option = $this->stringKeyedArray($option);
        $priority = $option['priority'] ?? null;

        return is_int($priority) && $priority > 0 ? $priority : PHP_INT_MAX;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function externalUrl(mixed $value): ?string
    {
        $url = $this->nullableString($value);

        return $url !== null && preg_match('/^https?:\/\//i', $url) === 1 ? $url : null;
    }

    /** @return array<string, mixed>|null */
    private function stringKeyedArray(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                return null;
            }
            $result[$key] = $item;
        }

        return $result;
    }
}
