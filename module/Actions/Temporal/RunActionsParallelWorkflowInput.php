<?php

declare(strict_types=1);

namespace Module\Actions\Temporal;

final readonly class RunActionsParallelWorkflowInput
{
    /**
     * @param  list<string>              $beforeIds
     * @param  list<string>              $actionIds
     * @param  list<string>              $afterIds
     * @param  list<string>              $onErrorActionIds
     * @param  array<string, string>     $codeMap
     * @param  array<string, mixed>      $context
     * @param  array<string, list<int>>  $backoffByActionId
     * @param  array<string, int>        $delayBeforeByActionId
     */
    public function __construct(
        public array $beforeIds,
        public array $actionIds,
        public array $afterIds,
        public array $onErrorActionIds,
        public array $codeMap,
        public array $context,
        public array $backoffByActionId,
        public array $delayBeforeByActionId,
        public ?string $scenarioRunId,
    ) {}
}
