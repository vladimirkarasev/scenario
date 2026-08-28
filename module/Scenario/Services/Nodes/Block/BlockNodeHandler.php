<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Nodes\Block\Fields\BlockFieldFactory;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeDataReader;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Runtime\ScenarioRunVersionResolver;
use Module\Scenario\Services\Variables\VariableResolver;

final readonly class BlockNodeHandler implements NodeHandlerInterface
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private BlockNodeValidator $validator,
        private NodeDataReader $nodeData,
        private ScenarioRunVersionResolver $runVersions,
        private BlockFieldFactory $fields,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        return !$this->nodeData->boolean($this->nodeData->data($node), 'skipInSurvey');
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        return NodeAdvanceResult::next(
            $this->graphResolver->defaultNextNodeId($this->runVersions->resolve($run), $this->nodeData->id($node)),
        );
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        $this->validator->validate($this->nodeData->data($node), $data->input);

        $blockKey = $this->nodeData->id($node);
        $context = $run->context ?? [];
        $existing = is_array($context[$blockKey] ?? null) ? $context[$blockKey] : [];

        $run->forceFill([
            'context' => array_merge($context, [$blockKey => array_merge($existing, $data->input)]),
        ])->save();

        return $this->graphResolver->defaultNextNodeId($this->runVersions->resolve($run), $blockKey);
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        $data = $this->nodeData->data($node);
        $blocks = $this->nodeData->array($data, 'blocks');
        $text = $this->nodeData->string($data, 'text');

        if ($text !== '') {
            array_unshift(
                $blocks,
                $this->fields->richText($this->nodeData->id($node).'_text', $data['text'] ?? null)->toArray(),
            );
        }

        $rawBlockContext = $context[$this->nodeData->id($node)] ?? null;
        /** @var array<string, mixed> $blockContext */
        $blockContext = is_array($rawBlockContext) ? $rawBlockContext : [];

        foreach ($this->nodeData->array($data, 'fields') as $index => $field) {
            if (is_array($field) && is_int($index)) {
                $fieldData = [];
                foreach ($field as $key => $value) {
                    if (is_string($key)) {
                        $fieldData[$key] = $value;
                    }
                }
                $blocks[] = $this->fields->create($fieldData, $index, $blockContext)->toArray();
            }
        }

        $layoutDocument = $data['layoutDocument'] ?? null;

        return [
            'type' => 'block',
            'title' => $this->variableResolver->resolve($this->nodeData->string($data, 'title'), $context),
            'hideTitle' => $this->nodeData->boolean($data, 'hideTitle', true),
            'blocks' => $this->resolveBlocksKeepingRawTemplates($blocks, $context),
            'layoutDocument' => is_array($layoutDocument)
                ? $this->variableResolver->resolve($layoutDocument, $context)
                : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     * @param  array<string, mixed>  $context
     */
    private function resolveBlocksKeepingRawTemplates(array $blocks, array $context): mixed
    {
        $rawLabelTemplates = [];
        $rawDetailDocuments = [];

        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }

            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            if (isset($props['labelTemplate']) && is_string($props['labelTemplate'])) {
                $rawLabelTemplates[$index] = $props['labelTemplate'];
            }
            if (isset($props['detailDocument']) && is_array($props['detailDocument'])) {
                $rawDetailDocuments[$index] = $props['detailDocument'];
            }
        }

        $resolved = $this->variableResolver->resolve($blocks, $context);

        if (!is_array($resolved)) {
            return $resolved;
        }

        foreach ($rawLabelTemplates as $index => $template) {
            if (isset($resolved[$index]) && is_array($resolved[$index])) {
                $props = is_array($resolved[$index]['props'] ?? null) ? $resolved[$index]['props'] : [];
                $resolved[$index]['props'] = [...$props, 'labelTemplate' => $template];
            }
        }

        foreach ($rawDetailDocuments as $index => $document) {
            if (isset($resolved[$index]) && is_array($resolved[$index])) {
                $props = is_array($resolved[$index]['props'] ?? null) ? $resolved[$index]['props'] : [];
                $resolved[$index]['props'] = [...$props, 'detailDocument' => $document];
            }
        }

        return $resolved;
    }
}
