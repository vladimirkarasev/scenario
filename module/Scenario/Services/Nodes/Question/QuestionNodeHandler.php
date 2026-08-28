<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Question;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\Block\BlockNodeHandler;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;

final readonly class QuestionNodeHandler implements NodeHandlerInterface
{
    public function __construct(private BlockNodeHandler $blocks)
    {
    }

    public function isInteractive(array $node): bool
    {
        return $this->blocks->isInteractive($node);
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        return $this->blocks->advance($run, $node);
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        return $this->blocks->continueFrom($run, $node, $data);
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        return [...$this->blocks->render($version, $node, $context), 'type' => 'question'];
    }
}
