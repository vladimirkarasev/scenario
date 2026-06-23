<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers\Concerns;

use Module\Actions\DTO\ActionConfigField;

trait HasNoConfigFields
{
    /** @return iterable<ActionConfigField> */
    public function configFields(): iterable
    {
        return [];
    }
}
