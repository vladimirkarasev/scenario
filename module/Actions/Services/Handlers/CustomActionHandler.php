<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\Concerns\HasNoConfigFields;

final class CustomActionHandler implements ActionHandlerInterface
{
    use HasNoConfigFields;

    public function __construct(
        private readonly ActionDataResolver $dataResolver,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        return ActionResult::success([
            'message' => 'Custom action handler placeholder executed.',
            'config' => $this->dataResolver->resolve($action->config ?? [], $this->dataResolver->contextForAction($action, $input)),
            'input' => $input,
        ]);
    }
}
