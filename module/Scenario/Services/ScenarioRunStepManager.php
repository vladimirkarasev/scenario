<?php

declare(strict_types=1);

namespace Module\Scenario\Services;

use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Repositories\ScenarioRunStepRepository;

final readonly class ScenarioRunStepManager
{
    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private ScenarioRunStepRepository $steps,
    ) {}

    /**
     * Создаёт открытый шаг для узла, если его ещё нет.
     *
     * @param array<string, mixed> $node
     */
    public function ensureOpen(ScenarioRun $run, array $node): void
    {
        $nodeId = isset($node['id']) && is_string($node['id']) ? $node['id'] : throw new \RuntimeException('Node has no id.');
        $nodeType = isset($node['type']) && is_string($node['type']) ? $node['type'] : throw new \RuntimeException('Node has no type.');

        $existing = $this->steps->openForNode($run, $nodeId);

        if ($existing !== null) {
            return;
        }

        $this->steps->create($run, [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'entered_at' => now(),
        ]);
    }

    /**
     * Создаёт завершённый шаг для неинтерактивного узла.
     *
     * @param array<string, mixed> $node
     * @param array<string, mixed> $output
     */
    public function createAuto(ScenarioRun $run, array $node, array $output): void
    {
        $nodeId = isset($node['id']) && is_string($node['id']) ? $node['id'] : throw new \RuntimeException('Node has no id.');
        $nodeType = isset($node['type']) && is_string($node['type']) ? $node['type'] : throw new \RuntimeException('Node has no type.');

        $this->steps->create($run, [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'entered_at' => now(),
            'exited_at' => now(),
            'output' => $output,
        ]);
    }

    /**
     * Закрывает текущий открытый шаг, создавая его если не существует.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     */
    public function closeOpen(ScenarioRun $run, array $input, array $output): void
    {
        $step = $this->steps->latestOpenForCurrentNode($run);

        if ($step === null) {
            $currentNodeId = $run->current_node_id ?? '';
            $nodeType = 'unknown';
            $version = $run->version;

            if ($currentNodeId !== '' && $version !== null) {
                $foundNode = $this->graphResolver->findNode($version, $currentNodeId);
                $rawType = $foundNode['type'] ?? null;
                $nodeType = is_string($rawType) ? $rawType : 'unknown';
            }

            $step = $this->steps->create($run, [
                'node_id' => $currentNodeId,
                'node_type' => $nodeType,
                'entered_at' => now(),
            ]);
        }

        $this->steps->update($step, [
            'input' => $input,
            'output' => $output,
            'exited_at' => now(),
        ]);
    }
}
