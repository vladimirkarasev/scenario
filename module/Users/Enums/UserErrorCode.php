<?php

declare(strict_types=1);

namespace Module\Users\Enums;

use App\Contracts\ErrorText;

/**
 * Коды ошибок модуля Users и их текст (title/detail). Единственное место с
 * текстом ошибок — сюда подключается локализация (`__()`), throw-сайты не трогаем.
 */
enum UserErrorCode: string implements ErrorText
{
    case UserNotFound = 'USER_NOT_FOUND';
    case SystemUserImmutable = 'SYSTEM_USER_IMMUTABLE';
    case SelfDeleteForbidden = 'SELF_DELETE_FORBIDDEN';
    case LastAdministrator = 'LAST_ADMINISTRATOR';
    case NoProjectContext = 'NO_PROJECT_CONTEXT';

    case RoleNotFound = 'ROLE_NOT_FOUND';
    case SystemRoleImmutable = 'SYSTEM_ROLE_IMMUTABLE';
    case RoleInUse = 'ROLE_IN_USE';

    case ManageAdminForbidden = 'MANAGE_ADMIN_FORBIDDEN';
    case ManagePrivilegedForbidden = 'MANAGE_PRIVILEGED_FORBIDDEN';
    case RoleAssignForbidden = 'ROLE_ASSIGN_FORBIDDEN';
    case RoleEscalationForbidden = 'ROLE_ESCALATION_FORBIDDEN';
    case PermissionEscalationForbidden = 'PERMISSION_ESCALATION_FORBIDDEN';
    case RoleManageForbidden = 'ROLE_MANAGE_FORBIDDEN';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function title(): string
    {
        return (string) trans("errors.users.{$this->value}.title");
    }

    #[\Override]
    public function detail(): string
    {
        return (string) trans("errors.users.{$this->value}.detail");
    }
}
