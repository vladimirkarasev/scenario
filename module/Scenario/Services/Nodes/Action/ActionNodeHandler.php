<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Action;

use Module\Scenario\DTO\ScenarioRunContinueData;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Services\Nodes\NodeAdvanceResult;
use Module\Scenario\Services\Nodes\NodeHandlerInterface;
use Module\Scenario\Services\Nodes\NodeHelpers;
use Module\Scenario\Services\Nodes\RetryableNodeHandler;
use Module\Scenario\Services\ScenarioGraphResolver;
use Module\Scenario\Services\VariableResolver;

/**
 * Lifecycle action-ноды: решает «когда» запускать/паузить/продвигать прогон.
 * Вся механика выполнения (сбор экшенов, запуск, повтор, статусы стадий) — в ActionNodePipeline.
 */
final readonly class ActionNodeHandler implements NodeHandlerInterface, RetryableNodeHandler
{
    use NodeHelpers;

    public function __construct(
        private ScenarioGraphResolver $graphResolver,
        private VariableResolver $variableResolver,
        private ActionNodePipeline $pipeline,
    ) {
    }

    public function isInteractive(array $node): bool
    {
        // wait_for_result-ноды обрабатываются через advance() (async-пауза с pipeline),
        // поэтому интерактивными (ожидающими ввода пользователя) не считаются.
        if ($this->boolField($this->nodeData($node), 'wait_for_result')) {
            return false;
        }

        return !$this->boolField($this->nodeData($node), 'skipInSurvey');
    }

    public function advance(ScenarioRun $run, array $node): NodeAdvanceResult
    {
        $nodeId = $this->nodeId($node);

        // Обычная (fire-and-forget) нода: запускаем и идём дальше.
        if (!$this->boolField($this->nodeData($node), 'wait_for_result')) {
            $this->pipeline->dispatch($run, $node);

            return NodeAdvanceResult::next($this->nextNodeId($run, $nodeId));
        }

        // wait_for_result: пауза с pipeline по WS.
        $state = $this->pipeline->state($this->runContext($run), $nodeId);

        // Уже завершилось (резюм проставил done) — продвигаемся.
        if ($state === ActionStatus::Done) {
            return NodeAdvanceResult::next($this->nextNodeId($run, $nodeId));
        }

        // Первый заход — запускаем цепочку. Если запускать нечего — сразу дальше.
        if ($state === null) {
            $this->pipeline->markState($run, $nodeId, ActionStatus::Running);

            if (!$this->pipeline->dispatch($run, $node, $nodeId)) {
                $this->pipeline->markState($run, $nodeId, ActionStatus::Done);

                return NodeAdvanceResult::next($this->nextNodeId($run, $nodeId));
            }
        }

        // running / failed — ждём завершения цепочки по WS.
        return NodeAdvanceResult::pause();
    }

    public function continueFrom(ScenarioRun $run, array $node, ScenarioRunContinueData $data): ?string
    {
        $nodeId = $this->nodeId($node);

        // Ручное «Продолжить» после ошибки в pipeline: экшены уже отработали,
        // не пере-запускаем — помечаем ноду завершённой и идём дальше.
        if ($this->boolField($this->nodeData($node), 'wait_for_result')) {
            $this->pipeline->markState($run, $nodeId, ActionStatus::Done);

            return $this->nextNodeId($run, $nodeId);
        }

        $this->pipeline->dispatch($run, $node);

        return $this->nextNodeId($run, $nodeId);
    }

    public function retry(ScenarioRun $run, array $node): bool
    {
        return $this->pipeline->retry($run, $node);
    }

    public function render(ScenarioVersion $version, array $node, array $context): array
    {
        $data = $this->nodeData($node);
        $nodeId = $this->nodeId($node);

        $results = $this->pipeline->stageResults($context, $nodeId);
        $failed = in_array(ActionStatus::Failed->value, $results, true)
            || $this->pipeline->state($context, $nodeId) === ActionStatus::Failed;

        return [
            'type' => $this->nodeType($node),
            'data' => $this->variableResolver->resolve($data, $context),
            'wait_for_result' => $this->boolField($data, 'wait_for_result'),
            'stages' => $this->pipeline->stages($node),
            'results' => $results,
            'failed' => $failed,
        ];
    }

    private function nextNodeId(ScenarioRun $run, string $nodeId): ?string
    {
        return $this->graphResolver->defaultNextNodeId($this->runVersion($run), $nodeId);
    }

    /** @return array<string, mixed> */
    private function runContext(ScenarioRun $run): array
    {
        return is_array($run->context) ? $run->context : [];
    }
}
