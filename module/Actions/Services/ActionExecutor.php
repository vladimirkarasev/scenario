<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Throwable;

final class ActionExecutor
{
    public function __construct(
        private readonly ActionRegistry $actionRegistry,
        private readonly ActionLogger $actionLogger,
    ) {
    }

    /** @param  array<string, mixed>  $input */
    public function execute(Action $action, array $input = [], int $attemptsCount = 1): ActionResult
    {
        if (!$action->is_active) {
            return ActionResult::skipped('Action is disabled.');
        }

        $run = $this->actionLogger->start($action, $input, $attemptsCount);

        try {
            $result = $this->actionRegistry
                ->handlerFor($action->type)
                ->handle($action, $input);
        } catch (Throwable $exception) {
            $result = ActionResult::failed($exception->getMessage());
        }

        $this->actionLogger->complete($run, $result);

        return $result;
    }
}
