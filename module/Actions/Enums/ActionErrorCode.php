<?php

declare(strict_types=1);

namespace Module\Actions\Enums;

use App\Contracts\ErrorText;

enum ActionErrorCode: string implements ErrorText
{
    case CategoryNotFound = 'ACTION_CATEGORY_NOT_FOUND';
    case SystemCategoryDeleteForbidden = 'ACTION_SYSTEM_CATEGORY_DELETE_FORBIDDEN';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function title(): string
    {
        return (string) trans("errors.actions.{$this->value}.title");
    }

    #[\Override]
    public function detail(): string
    {
        return (string) trans("errors.actions.{$this->value}.detail");
    }
}
