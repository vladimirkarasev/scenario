<?php

declare(strict_types=1);

namespace Module\Actions\Temporal;

final readonly class RunActionsWorkflowInput
{
    /**
     * @param  list<string>              $actionIds
     * @param  list<string>              $onErrorActionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, mixed>      $context
     * @param  array<string, string>     $scopeMap
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     */
    public function __construct(
        public array $actionIds,
        public array $onErrorActionIds,
        public array $codeMap,
        public array $context,
        public array $scopeMap,
        public array $backoffByActionId,
        public array $delayBeforeByActionId,
        public ?string $scenarioRunId,
        public ?string $scenarioNodeId,
        public ?string $actionNodeId = null,
    ) {}
}
