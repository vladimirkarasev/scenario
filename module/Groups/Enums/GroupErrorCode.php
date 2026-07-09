<?php

declare(strict_types=1);

namespace Module\Groups\Enums;

use App\Contracts\ErrorText;

/**
 * Коды ошибок модуля Groups. Текст — в lang/<locale>/errors.php (errors.groups.*).
 */
enum GroupErrorCode: string implements ErrorText
{
    case GroupNotFound = 'GROUP_NOT_FOUND';
    case ExternalIdConflict = 'GROUP_EXTERNAL_ID_CONFLICT';
    case MemberUserNotFound = 'GROUP_MEMBER_USER_NOT_FOUND';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function title(): string
    {
        return (string) trans("errors.groups.{$this->value}.title");
    }

    #[\Override]
    public function detail(): string
    {
        return (string) trans("errors.groups.{$this->value}.detail");
    }
}
