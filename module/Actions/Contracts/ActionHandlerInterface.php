<?php

declare(strict_types=1);

namespace Module\Actions\Contracts;

use Module\Actions\DTO\ActionConfigField;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;

interface ActionHandlerInterface
{
    /** @param  array<string, mixed>  $input */
    public function handle(Action $action, array $input = []): ActionResult;

    /** @return iterable<ActionConfigField> */
    public function configFields(): iterable;
}
