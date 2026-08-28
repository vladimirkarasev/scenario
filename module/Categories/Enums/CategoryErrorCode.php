<?php

declare(strict_types=1);

namespace Module\Categories\Enums;

use App\Contracts\ErrorText;

enum CategoryErrorCode: string implements ErrorText
{
    case ManageForbidden = 'CATEGORY_MANAGE_FORBIDDEN';
    case ParentCycle = 'CATEGORY_PARENT_CYCLE';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function title(): string
    {
        return (string) trans("errors.categories.{$this->value}.title");
    }

    #[\Override]
    public function detail(): string
    {
        return (string) trans("errors.categories.{$this->value}.detail");
    }
}
