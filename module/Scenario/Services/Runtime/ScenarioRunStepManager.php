<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use Illuminate\Contracts\Events\Dispatcher;
use Module\Scenario\Events\ScenarioNodeEntered;
use Module\Scenario\Events\ScenarioNodeExited;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Repositories\ScenarioRunStepRepository;
use Module\Scenario\Services\Graph\ScenarioGraphResolver;
use Module\Scenario\Services\Nodes\NodeDataReader;

final readonly class ScenarioRunStepManager
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private ScenarioRunStepRepository $steps,
        private Dispatcher $events,
        private NodeDataReader $nodeData,
    ) {
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function ensureOpen(ScenarioRun $run, array $node): void
    {
        $nodeId = $this->nodeData->id($node);
        $nodeType = $this->nodeData->type($node);

        $existing = $this->steps->openForNode($run, $nodeId);

        if ($existing !== null) {
            return;
        }

        $step = $this->steps->create($run, [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'entered_at' => now(),
        ]);

        $this->events->dispatch(new ScenarioNodeEntered($run, $node, $step));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $output
     */
    public function createAuto(ScenarioRun $run, array $node, array $output): void
    {
        $nodeId = isset($node['id']) && is_string($node['id']) ? $node['id'] : throw new \RuntimeException(
            'Node has no id.'
        );
        $nodeType = isset($node['type']) && is_string($node['type']) ? $node['type'] : throw new \RuntimeException(
            'Node has no type.'
        );

        $this->steps->create($run, [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'entered_at' => now(),
            'exited_at' => now(),
            'output' => $output,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public function closeOpen(ScenarioRun $run, array $input, array $output): void
    {
        $step = $this->steps->latestOpenForCurrentNode($run);
        $justCreated = false;

        if ($step === null) {
            $currentNodeId = $run->current_node_id ?? '';
            $foundNode = $currentNodeId !== '' ? $this->resolveNode($run, $currentNodeId) : null;
            $nodeType = is_string($foundNode['type'] ?? null) ? $foundNode['type'] : 'unknown';

            $step = $this->steps->create($run, [
                'node_id' => $currentNodeId,
                'node_type' => $nodeType,
                'entered_at' => now(),
            ]);

            $justCreated = true;
        }

        $this->steps->update($step, [
            'input' => $input,
            'output' => $output,
            'exited_at' => now(),
        ]);

        $node = $this->resolveNode($run, $step->node_id) ?? ['id' => $step->node_id, 'type' => $step->node_type->value];

        if ($justCreated) {
            $this->events->dispatch(new ScenarioNodeEntered($run, $node, $step));
        }

        $this->events->dispatch(new ScenarioNodeExited($run, $node, $step, $input, $output));
    }

    /** @return array<string, mixed>|null */
    private function resolveNode(ScenarioRun $run, string $nodeId): ?array
    {
        $version = $run->version;

        if (!$version instanceof ScenarioVersion) {
            return null;
        }

        try {
            return $this->graphResolver->findNode($version, $nodeId);
        } catch (\Throwable) {
            return null;
        }
    }
}
